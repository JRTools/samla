<?php
declare(strict_types=1);
namespace Samla\Core\Storage;

use Samla\PluginApi\{AccessDenied, AuthorizationChecker, AuthorizationRequest, Decision, MutationConflict, MutationContext, MutationRunner, Scope, StorageChange};

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
        $query = $this->pdo->prepare('SELECT id, name, kind, size_bytes FROM storage_entries WHERE space_id = ? AND parent_id = ? AND id > ? AND trashed_at IS NULL ORDER BY id LIMIT 101');
        $query->execute([$space, $parent, $after ?? '']); $rows = $query->fetchAll(); $next = null;
        if (count($rows) > 100) { array_pop($rows); $next = $rows[99]['id']; }
        $used = $this->pdo->prepare('SELECT COALESCE(SUM(size_bytes), 0) FROM storage_entries WHERE space_id = ?'); $used->execute([$space]);
        return ['space' => $catalog, 'folder' => $folder, 'parent' => $parent, 'rows' => $rows, 'next' => $next,
            'used' => (int) $used->fetchColumn(), 'can_create' => $this->allowed($actor, 'files.create', $space),
            'can_read' => $this->allowed($actor, 'files.read', $space), 'can_restore' => $this->allowed($actor, 'files.restore', $space)];
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
            $blob === null ? 'storage.folder.create' : 'storage.file.upload', Scope::Space, $space, $targets,
            storageChange: new StorageChange($name, $parent, $blob['size'] ?? null, $blob['sha256'] ?? null));
        $guard = function () use ($actor, $space, $parent): void {
            $this->requireAccess($actor, 'files.create', $space); $this->space($space); $this->parent($space, $parent, 1);
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
        $query = $this->pdo->prepare("SELECT name, parent_id, blob_id, size_bytes, sha256 FROM storage_entries WHERE id = ? AND space_id = ? AND kind = 'file' AND trashed_at IS NULL");
        $query->execute([$id, $space]); $row = $query->fetch();
        if ($row === false) { throw new \InvalidArgumentException('Unknown file.'); }
        $this->parent($space, $row['parent_id']);
        return ['row' => $row, 'stream' => $this->content->open($row['blob_id'], (int) $row['size_bytes'])];
    }
    private function space(string $space): array
    {
        $query = $this->pdo->prepare('SELECT id, name, quota_bytes FROM storage_spaces WHERE id = ?'); $query->execute([$space]);
        $row = $query->fetch(); if ($row === false) { throw new \InvalidArgumentException('Unknown space.'); } return $row;
    }
    private function parent(string $space, string $parent, int $reserve = 0): ?array
    {
        if ($parent === '') { return null; }
        $first = null; $seen = [];
        while ($parent !== '') {
            if (isset($seen[$parent]) || count($seen) >= 128 - $reserve) { throw new \InvalidArgumentException('Invalid folder ancestry.'); }
            $seen[$parent] = true;
            $query = $this->pdo->prepare("SELECT id, name, parent_id FROM storage_entries WHERE space_id = ? AND id = ? AND kind = 'folder' AND trashed_at IS NULL");
            $query->execute([$space, $parent]); $row = $query->fetch();
            if ($row === false) { throw new \InvalidArgumentException('Invalid parent folder.'); }
            $first ??= $row; $parent = $row['parent_id'];
        }
        return $first;
    }

    public function trashListing(string $actor, string $space, ?string $after = null): array
    {
        $this->requireAccess($actor, 'files.list', $space); $this->requireAccess($actor, 'files.restore', $space);
        $query = $this->pdo->prepare('SELECT id, parent_id, name, kind, size_bytes FROM storage_entries WHERE space_id = ? AND trashed_at IS NOT NULL AND id > ? ORDER BY id LIMIT 101');
        $query->execute([$space, $after ?? '']); $rows = $query->fetchAll(); $next = null;
        if (count($rows) > 100) { array_pop($rows); $next = $rows[99]['id']; }
        return ['space' => $this->space($space), 'rows' => $rows, 'next' => $next];
    }
    public function details(string $actor, string $space, string $id): array
    {
        $this->requireAccess($actor, 'files.list', $space); $row = $this->entry($space, $id); $this->parent($space, $row['parent_id']);
        if ($row['trashed_at'] !== null) { throw new \InvalidArgumentException('Entry is trashed.'); }
        return $row;
    }
    public function folderChoices(string $actor, string $space): array
    {
        $this->requireAccess($actor, 'files.list', $space);
        $query = $this->pdo->prepare("SELECT id FROM storage_entries WHERE space_id = ? AND kind = 'folder' AND trashed_at IS NULL ORDER BY id LIMIT 100"); $query->execute([$space]);
        $choices = ['' => 'Bereichswurzel'];
        foreach ($query->fetchAll(\PDO::FETCH_COLUMN) as $id) {
            try {
                $this->parent($space, $id); $parts = []; $current = $id;
                while ($current !== '') { $row = $this->entry($space, $current); array_unshift($parts, $row['name']); $current = $row['parent_id']; }
                $choices[$id] = implode(' / ', $parts);
            } catch (\InvalidArgumentException) { /* A trashed ancestor makes this destination unavailable. */ }
        }
        return $choices;
    }
    /** A single-entry lifecycle operation; folder copy is deliberately not recursive. */
    public function change(string $actor, string $space, string $id, string $operation, string $expectedName,
        string $expectedParent, ?string $name = null, string $parent = ''): string
    {
        $rights = ['rename' => ['files.update'], 'move' => ['files.update','files.create','files.delete'],
            'copy' => ['files.read','files.create'], 'trash' => ['files.delete'], 'restore' => ['files.restore','files.create']];
        if (!isset($rights[$operation])) { throw new \InvalidArgumentException('Unknown storage operation.'); }
        if ($name !== null) { EntryName::validate($name); }
        $newId = $operation === 'copy' ? bin2hex(random_bytes(16)) : $id;
        $context = new MutationContext(bin2hex(random_bytes(16)), $actor,
            $operation === 'copy' ? 'storage.file.copy' : 'storage.entry.' . $operation, Scope::Space, $space,
            ['entry' => $id, 'result' => $newId], storageChange: new StorageChange($name, $parent));
        $guard = function () use ($actor, $space, $id, $operation, $rights, $expectedName, $expectedParent, $parent): void {
            foreach ($rights[$operation] as $action) { $this->requireAccess($actor, $action, $space); }
            $row = $this->entry($space, $id);
            if ($row['name'] !== $expectedName || $row['parent_id'] !== $expectedParent) { throw new MutationConflict(); }
            if (($operation === 'restore') !== ($row['trashed_at'] !== null)) { throw new MutationConflict(); }
            $this->parent($space, $row['parent_id']);
            if (in_array($operation, ['move','copy'], true)) {
                $this->parent($space, $parent); $ancestor = $parent; $seen = 0;
                while ($ancestor !== '') {
                    if ($ancestor === $id || ++$seen > 128) { throw new \InvalidArgumentException('Folder cycle.'); }
                    $ancestor = $this->entry($space, $ancestor)['parent_id'];
                }
                if ($operation === 'move' && $row['kind'] === 'folder' && $seen + $this->subtreeHeight($space, $id) > 128) {
                    throw new \InvalidArgumentException('Folder tree would be too deep.');
                }
            }
            if ($operation === 'copy' && $row['kind'] !== 'file') { throw new \InvalidArgumentException('Only individual files can be copied.'); }
        };
        $this->mutations->run($context, $guard, function () use ($space, $id, $newId, $operation, $name, $parent): bool {
            $row = $this->entry($space, $id);
            if ($operation === 'trash' || $operation === 'restore') {
                $query = $this->pdo->prepare('UPDATE storage_entries SET trashed_at = ? WHERE id = ?');
                $query->execute([$operation === 'trash' ? time() : null, $id]); return true;
            }
            $newName = $name ?? $row['name']; $newParent = $operation === 'rename' ? $row['parent_id'] : $parent;
            if ($operation !== 'copy' && $newName === $row['name'] && $newParent === $row['parent_id']) { return false; }
            $query = $this->pdo->prepare('SELECT 1 FROM storage_entries WHERE space_id = ? AND parent_id = ? AND name = ? AND id <> ?');
            $query->execute([$space, $newParent, $newName, $newId]); if ($query->fetchColumn() !== false) { throw new MutationConflict(); }
            if ($operation === 'copy') {
                $query = $this->pdo->prepare('SELECT COALESCE(SUM(size_bytes), 0) FROM storage_entries WHERE space_id = ?'); $query->execute([$space]);
                if ((int) $query->fetchColumn() + (int) $row['size_bytes'] > (int) $this->space($space)['quota_bytes']) { throw new \LengthException('Quota exceeded.'); }
                // Immutable content can safely be shared; quota still counts the logical copy.
                $stream = $this->content->open($row['blob_id'], (int) $row['size_bytes']); fclose($stream);
                $query = $this->pdo->prepare("INSERT INTO storage_entries (id, space_id, parent_id, name, kind, blob_id, size_bytes, sha256, created_at) VALUES (?, ?, ?, ?, 'file', ?, ?, ?, ?)");
                $query->execute([$newId, $space, $newParent, $newName, $row['blob_id'], $row['size_bytes'], $row['sha256'], time()]);
            } else {
                $query = $this->pdo->prepare('UPDATE storage_entries SET name = ?, parent_id = ? WHERE id = ?'); $query->execute([$newName, $newParent, $id]);
            }
            return true;
        });
        return $newId;
    }
    private function entry(string $space, string $id): array
    {
        $query = $this->pdo->prepare('SELECT * FROM storage_entries WHERE space_id = ? AND id = ?'); $query->execute([$space, $id]);
        $row = $query->fetch(); if ($row === false) { throw new \InvalidArgumentException('Unknown entry.'); } return $row;
    }
    private function subtreeHeight(string $space, string $id): int
    {
        $queue = [[$id, 1]]; $visited = []; $height = 1;
        while ($queue !== []) {
            [$parent, $depth] = array_shift($queue);
            if (isset($visited[$parent]) || count($visited) >= 1000) { throw new \InvalidArgumentException('Folder tree is too large or cyclic.'); }
            $visited[$parent] = true; $height = max($height, $depth);
            $query = $this->pdo->prepare("SELECT id FROM storage_entries WHERE space_id = ? AND parent_id = ? AND kind = 'folder' LIMIT 1001"); $query->execute([$space, $parent]);
            $children = $query->fetchAll(\PDO::FETCH_COLUMN);
            if (count($children) + count($queue) + count($visited) > 1000) { throw new \InvalidArgumentException('Folder tree is too large.'); }
            foreach ($children as $child) { $queue[] = [$child, $depth + 1]; }
        }
        return $height;
    }
}
