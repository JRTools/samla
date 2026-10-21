<?php
declare(strict_types=1);
namespace Samla\Core\Storage;

use Samla\PluginApi\{AccessDenied, AuthorizationChecker, AuthorizationRequest, Decision, MutationConflict, MutationContext, MutationRunner, Scope};

final readonly class FileService
{
    public function __construct(private \PDO $pdo, private AuthorizationChecker $authorization,
        private MutationRunner $mutations, private LocalContentStore $content) {}

    public function allowed(string $actor, string $action, string $space): bool
    { return $this->authorization->decide(new AuthorizationRequest($actor, $action, Scope::Space, $space))->decision === Decision::Permit; }
    private function requireAccess(string $actor, string $action, string $space): void
    { if (!$this->allowed($actor, $action, $space)) { throw new AccessDenied(); } }

    /** List only explicitly authorized spaces, with bounded catalog traversal. */
    public function spaces(string $actor, ?string $after = null): array
    {
        $query = $this->pdo->prepare('SELECT id, name, quota_bytes FROM storage_spaces WHERE id > ? ORDER BY id LIMIT 101');
        $query->execute([$after ?? '']); $rows = $query->fetchAll(); $next = null;
        if (count($rows) > 100) { array_pop($rows); $next = $rows[99]['id']; }
        return ['rows' => array_values(array_filter($rows, fn(array $row): bool => $this->allowed($actor, 'files.list', $row['id']))), 'next' => $next];
    }
    public function listing(string $actor, string $space, string $parent = '', ?string $after = null): array
    {
        $this->requireAccess($actor, 'files.list', $space);
        $catalog = $this->space($space); $folder = $this->parent($space, $parent);
        $query = $this->pdo->prepare('SELECT id, name, kind, size_bytes FROM storage_entries WHERE space_id = ? AND parent_id = ? AND id > ? ORDER BY id LIMIT 101');
        $query->execute([$space, $parent, $after ?? '']); $rows = $query->fetchAll(); $next = null;
        if (count($rows) > 100) { array_pop($rows); $next = $rows[99]['id']; }
        $used = $this->pdo->prepare('SELECT COALESCE(SUM(size_bytes), 0) FROM storage_entries WHERE space_id = ?'); $used->execute([$space]);
        return ['space' => $catalog, 'folder' => $folder, 'parent' => $parent, 'rows' => $rows, 'next' => $next,
            'used' => (int) $used->fetchColumn(), 'can_create' => $this->allowed($actor, 'files.create', $space),
            'can_read' => $this->allowed($actor, 'files.read', $space)];
    }
    public function createFolder(string $actor, string $space, string $parent, string $name): string
    { return $this->create($actor, $space, $parent, $name, null); }
    /** @param resource $source */
    public function upload(string $actor, string $space, string $parent, string $name, $source): string
    {
        // Reject unauthorized and invalid targets before reading or staging any content.
        $this->requireAccess($actor, 'files.create', $space); EntryName::validate($name);
        $this->space($space); $this->parent($space, $parent);
        $blob = $this->content->publish($source);
        // On ambiguous transaction failure keep the immutable object. Never delete a potentially committed object.
        return $this->create($actor, $space, $parent, $name, $blob);
    }
    private function create(string $actor, string $space, string $parent, string $name, ?array $blob): string
    {
        EntryName::validate($name); $id = bin2hex(random_bytes(16));
        $targets = ['entry' => $id]; if ($parent !== '') { $targets['parent'] = $parent; }
        $context = new MutationContext(bin2hex(random_bytes(16)), $actor,
            $blob === null ? 'storage.folder.create' : 'storage.file.upload', Scope::Space, $space, $targets);
        $guard = function () use ($actor, $space, $parent): void {
            $this->requireAccess($actor, 'files.create', $space); $this->space($space); $this->parent($space, $parent);
        };
        $this->mutations->run($context, $guard, function () use ($space, $parent, $name, $id, $blob): bool {
            $query = $this->pdo->prepare('SELECT 1 FROM storage_entries WHERE space_id = ? AND parent_id = ? AND name = ?');
            $query->execute([$space, $parent, $name]); if ($query->fetchColumn() !== false) { throw new MutationConflict(); }
            $catalog = $this->space($space);
            $query = $this->pdo->prepare('SELECT COALESCE(SUM(size_bytes), 0) FROM storage_entries WHERE space_id = ?'); $query->execute([$space]);
            if ((int) $query->fetchColumn() + ($blob['size'] ?? 0) > (int) $catalog['quota_bytes']) { throw new \LengthException('Space quota exceeded.'); }
            $query = $this->pdo->prepare('INSERT INTO storage_entries (id, space_id, parent_id, name, kind, blob_id, size_bytes, sha256, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $query->execute([$id, $space, $parent, $name, $blob === null ? 'folder' : 'file', $blob['id'] ?? null, $blob['size'] ?? 0, $blob['sha256'] ?? null, time()]);
            return true;
        });
        return $id;
    }
    /** @return array{row:array,stream:resource} */
    public function download(string $actor, string $space, string $id): array
    {
        $this->requireAccess($actor, 'files.read', $space);
        $query = $this->pdo->prepare("SELECT name, blob_id, size_bytes, sha256 FROM storage_entries WHERE id = ? AND space_id = ? AND kind = 'file'");
        $query->execute([$id, $space]); $row = $query->fetch();
        if ($row === false) { throw new \InvalidArgumentException('Unknown file.'); }
        return ['row' => $row, 'stream' => $this->content->open($row['blob_id'], (int) $row['size_bytes'])];
    }
    private function space(string $space): array
    {
        $query = $this->pdo->prepare('SELECT id, name, quota_bytes FROM storage_spaces WHERE id = ?'); $query->execute([$space]);
        $row = $query->fetch(); if ($row === false) { throw new \InvalidArgumentException('Unknown space.'); } return $row;
    }
    private function parent(string $space, string $parent): ?array
    {
        if ($parent === '') { return null; }
        $query = $this->pdo->prepare("SELECT id, name, parent_id FROM storage_entries WHERE space_id = ? AND id = ? AND kind = 'folder'");
        $query->execute([$space, $parent]); $row = $query->fetch();
        if ($row === false) { throw new \InvalidArgumentException('Invalid parent folder.'); } return $row;
    }
}
