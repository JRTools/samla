<?php
declare(strict_types=1);
namespace Samla\Core\Storage;

use Samla\PluginApi\{AccessDenied, AuthorizationChecker, AuthorizationRequest, Decision, MutationConflict, MutationContext, MutationRunner, Scope, StorageChange, StorageLockChange, StoragePurgeChange};

final readonly class FileService
{
    public function __construct(private \PDO $pdo, private AuthorizationChecker $authorization,
        private MutationRunner $mutations, private LocalContentStore $content, private ?StorageWriteGuard $writeLocks = null,
        private array|\Closure $lockTokens = []) {}

    private function proof(): array { return $this->lockTokens instanceof \Closure ? ($this->lockTokens)() : $this->lockTokens; }

    private function locks(): StorageWriteGuard { return $this->writeLocks ?? new StorageWriteGuard($this->pdo); }

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
        $query = $this->pdo->prepare('SELECT id, parent_id, name, kind, size_bytes, revision, created_at FROM storage_entries WHERE space_id = ? AND parent_id = ? AND id > ? AND trashed_at IS NULL ORDER BY id LIMIT 101');
        $query->execute([$space, $parent, $after ?? '']); $rows = $query->fetchAll(); $next = null;
        if (count($rows) > 100) { array_pop($rows); $next = $rows[99]['id']; }
        return ['space' => $catalog, 'folder' => $folder, 'parent' => $parent, 'rows' => $rows, 'next' => $next,
            'used' => StorageUsage::bytes($this->pdo, $space), 'upload_max_bytes' => $this->content->maxBytes, 'can_create' => $this->allowed($actor, 'files.create', $space),
            'can_read' => $this->allowed($actor, 'files.read', $space), 'can_export'=>$this->allowed($actor,'files.export',$space)&&$this->allowed($actor,'files.read',$space), 'can_restore' => $this->allowed($actor, 'files.restore', $space),
            'can_view_trash' => $this->allowed($actor, 'files.restore', $space) || $this->allowed($actor, 'files.purge', $space)];
    }
    public function createFolder(string $actor, string $space, string $parent, string $name): string
    { return $this->create($actor, $space, $parent, $name, null); }
    /** Bounded name search across one authorized space; never indexes file contents. */
    public function search(string $actor, string $space, string $text = '', string $kind = 'all', ?string $after = null): array
    {
        $this->requireAccess($actor, 'files.list', $space);
        $catalog = $this->space($space);
        if (strlen($text) > 512 || !mb_check_encoding($text, 'UTF-8') || mb_strlen($text, 'UTF-8') > 128
            || preg_match('/[\x00-\x1f\x7f]/', $text) || !in_array($kind, ['all','file','folder'], true)
            || ($after !== null && !preg_match('/^[a-f0-9]{32}$/D', $after))) { throw new \InvalidArgumentException('Invalid file search.'); }
        $q = $this->pdo->prepare('SELECT id,parent_id,name,kind,size_bytes,revision,trashed_at FROM storage_entries WHERE space_id = ? ORDER BY id LIMIT 10001');
        $q->execute([$space]); $entries = $q->fetchAll();
        if (count($entries) > 10000) { throw new \LengthException('Search catalog capacity reached.'); }
        $nodes = array_column($entries, null, 'id'); $rows = [];
        foreach ($entries as $row) {
            if ($row['trashed_at'] !== null || ($after !== null && strcmp($row['id'], $after) <= 0)
                || ($kind !== 'all' && $row['kind'] !== $kind)
                || ($text !== '' && mb_stripos($row['name'], $text, 0, 'UTF-8') === false)) { continue; }
            $parent = $row['parent_id']; $seen = [$row['id'] => true]; $parts = [];
            while ($parent !== '') {
                if (isset($seen[$parent]) || count($seen) >= 128 || !isset($nodes[$parent])
                    || $nodes[$parent]['kind'] !== 'folder' || $nodes[$parent]['trashed_at'] !== null) { continue 2; }
                $seen[$parent] = true; array_unshift($parts, $nodes[$parent]['name']); $parent = $nodes[$parent]['parent_id'];
            }
            unset($row['trashed_at']); $row['folder_path'] = implode(' / ', $parts); $rows[] = $row;
            if (count($rows) === 101) { break; }
        }
        $next = null; if (count($rows) > 100) { array_pop($rows); $next = $rows[99]['id']; }
        return ['space' => $catalog, 'rows' => $rows, 'next' => $next, 'search_text' => $text, 'search_kind' => $kind,
            'can_read' => $this->allowed($actor, 'files.read', $space)];
    }
    /** @param resource $source */
    public function upload(string $actor, string $space, string $parent, string $name, $source): string
    {
        // Reject unauthorized and invalid targets before reading or staging any content.
        $this->requireAccess($actor, 'files.create', $space); EntryName::validate($name);
        $this->space($space); $this->parent($space, $parent);
        $this->assertCatalogSpace($space);
        $this->locks()->assertWritable($actor,$space,$parent,$this->proof());
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
            $this->assertCatalogSpace($space);
            $this->locks()->assertWritable($actor,$space,$parent,$this->proof());
        };
        $this->mutations->run($context, $guard, function () use ($space, $parent, $name, $id, $blob): bool {
            $query = $this->pdo->prepare('SELECT 1 FROM storage_entries WHERE space_id = ? AND parent_id = ? AND name = ? AND trashed_at IS NULL');
            $query->execute([$space, $parent, $name]); if ($query->fetchColumn() !== false) { throw new MutationConflict(); }
            $catalog = $this->space($space);
            if (StorageUsage::bytes($this->pdo, $space) + ($blob['size'] ?? 0) > (int) $catalog['quota_bytes']) { throw new \LengthException('Space quota exceeded.'); }
            $query = $this->pdo->prepare('INSERT INTO storage_entries (id, space_id, parent_id, name, kind, blob_id, size_bytes, sha256, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $query->execute([$id, $space, $parent, $name, $blob === null ? 'folder' : 'file', $blob['id'] ?? null, $blob['size'] ?? 0, $blob['sha256'] ?? null, time()]);
            return true;
        });
        return $id;
    }
    /** Create an empty file and its exclusive lease in one guarded transaction. */
    public function createLockedFile(string $actor, string $space, string $parent, string $name, string $token,
        bool $recursive = false, int $seconds = 3600, string $owner = ''): string
    {
        EntryName::validate($name); StorageWriteGuard::token($token);
        if ($seconds < 1 || $seconds > 3600 || strlen($owner) > 4096 || preg_match('//u', $owner) !== 1) { throw new \InvalidArgumentException('Invalid lock request.'); }
        $id = bin2hex(random_bytes(16)); $lock = bin2hex(random_bytes(16));
        $guard = function () use ($actor, $space, $parent, $name): void {
            $this->requireAccess($actor, 'files.create', $space); $this->requireAccess($actor, 'files.update', $space);
            $catalog = $this->space($space); $this->parent($space, $parent, 1); $this->assertCatalogSpace($space);
            $this->locks()->assertWritable($actor, $space, $parent, $this->proof());
            foreach ($this->locks()->conflicts($space, $parent) as $lease) {
                if ((bool) $lease['recursive_lock']) { throw new StorageLocked('Overlapping exclusive lock.'); }
            }
            $q = $this->pdo->prepare('SELECT 1 FROM storage_entries WHERE space_id = ? AND parent_id = ? AND name = ? AND trashed_at IS NULL');
            $q->execute([$space, $parent, $name]); if ($q->fetchColumn() !== false) { throw new MutationConflict(); }
            $q = $this->pdo->prepare('SELECT COUNT(*) FROM storage_locks WHERE space_id = ? AND expires_at > ?'); $q->execute([$space, $this->locks()->now()]);
            if ((int) $q->fetchColumn() >= 1000) { throw new \LengthException('Lock capacity reached.'); }
            if (StorageUsage::bytes($this->pdo, $space) > (int) $catalog['quota_bytes']) { throw new \LengthException('Space quota exceeded.'); }
        };
        $guard(); $empty = fopen('php://temp', 'w+b');
        try { $blob = $this->content->publish($empty); } finally { fclose($empty); }
        $targets = ['entry' => $id, 'lock' => $lock]; if ($parent !== '') { $targets['parent'] = $parent; }
        $context = new MutationContext(bin2hex(random_bytes(16)), $actor, 'storage.file.create_locked', Scope::Space, $space, $targets,
            storageChange: new StorageChange($name, $parent, $blob['size'], $blob['sha256']), storageLock: new StorageLockChange($recursive, $seconds));
        $this->mutations->run($context, $guard, function () use ($id, $lock, $space, $parent, $name, $token, $recursive, $seconds, $owner, $actor, $blob): bool {
            $now = $this->locks()->now();
            $q = $this->pdo->prepare("INSERT INTO storage_entries (id, space_id, parent_id, name, kind, blob_id, size_bytes, sha256, created_at) VALUES (?, ?, ?, ?, 'file', ?, 0, ?, ?)");
            $q->execute([$id, $space, $parent, $name, $blob['id'], $blob['sha256'], $now]);
            $q = $this->pdo->prepare('DELETE FROM storage_locks WHERE space_id = ? AND expires_at <= ?'); $q->execute([$space, $now]);
            $q = $this->pdo->prepare('INSERT INTO storage_locks (id, token, space_id, entry_id, actor_id, recursive_lock, owner_label, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $q->execute([$lock, $token, $space, $id, $actor, $recursive ? 1 : 0, $owner, $now + $seconds]); return true;
        });
        return $id;
    }
    private function assertCatalogSpace(string $space): void
    {
        $q = $this->pdo->prepare('SELECT COUNT(*) FROM storage_entries WHERE space_id = ?'); $q->execute([$space]);
        if ((int) $q->fetchColumn() >= 10000) { throw new \LengthException('Storage catalog capacity reached.'); }
    }
    /** @return array{row:array,stream:resource} */
    public function download(string $actor, string $space, string $id, ?int $expectedRevision = null): array
    {
        $this->requireAccess($actor, 'files.read', $space);
        $query = $this->pdo->prepare("SELECT name, parent_id, blob_id, size_bytes, sha256, revision FROM storage_entries WHERE id = ? AND space_id = ? AND kind = 'file' AND trashed_at IS NULL");
        $query->execute([$id, $space]); $row = $query->fetch();
        if ($row === false) { throw new \InvalidArgumentException('Unknown file.'); }
        if ($expectedRevision !== null && (int) $row['revision'] !== $expectedRevision) { throw new MutationConflict(); }
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
        $this->requireAccess($actor, 'files.list', $space);
        $restore = $this->allowed($actor, 'files.restore', $space); $purge = $this->allowed($actor, 'files.purge', $space);
        if (!$restore && !$purge) { throw new AccessDenied(); }
        $query = $this->pdo->prepare('SELECT id, parent_id, name, kind, size_bytes, revision FROM storage_entries WHERE space_id = ? AND trashed_at IS NOT NULL AND id > ? ORDER BY id LIMIT 101');
        $query->execute([$space, $after ?? '']); $rows = $query->fetchAll(); $next = null;
        if (count($rows) > 100) { array_pop($rows); $next = $rows[99]['id']; }
        return ['space' => $this->space($space), 'rows' => $rows, 'next' => $next, 'can_restore' => $restore, 'can_purge' => $purge];
    }
    public function purge(string $actor, string $space, string $id, int $expectedRevision): void
    {
        $this->requireAccess($actor, 'files.list', $space); $this->requireAccess($actor, 'files.purge', $space);
        $expected = $this->entry($space, $id);
        if ($expected['trashed_at'] === null || (int) $expected['revision'] !== $expectedRevision) { throw new MutationConflict(); }
        $proposal = new StoragePurgeChange($this->purgeSnapshot($space, $id));
        $context = new MutationContext(bin2hex(random_bytes(16)), $actor, 'storage.entry.purge', Scope::Space, $space, ['entry' => $id],
            storageChange: new StorageChange($expected['name'], $expected['parent_id']), storagePurge: $proposal);
        $this->mutations->run($context, function () use ($actor, $space, $id, $expected, $expectedRevision, $proposal): void {
            $this->requireAccess($actor, 'files.list', $space); $this->requireAccess($actor, 'files.purge', $space);
            $row = $this->entry($space, $id);
            if ($row['trashed_at'] === null || (int) $row['revision'] !== $expectedRevision || $row['name'] !== $expected['name'] || $row['parent_id'] !== $expected['parent_id']
                || $this->purgeSnapshot($space, $id) !== $proposal->entryRevisions) { throw new MutationConflict(); }
            $this->locks()->assertWritable($actor, $space, $id, $this->proof(), $row['kind'] === 'folder', true);
        }, function () use ($space, $proposal): bool {
            $ids = array_keys($proposal->entryRevisions); $marks = implode(',', array_fill(0, count($ids), '?'));
            $q = $this->pdo->prepare('DELETE FROM storage_locks WHERE space_id = ? AND entry_id IN (' . $marks . ')'); $q->execute([$space, ...$ids]);
            $q = $this->pdo->prepare('DELETE FROM storage_entries WHERE space_id = ? AND id IN (' . $marks . ')'); $q->execute([$space, ...$ids]);
            if ($q->rowCount() !== count($ids)) { throw new MutationConflict(); } return true;
        });
        // No unlink here. The separate GC applies its existing reference and object-age checks.
    }
    private function purgeSnapshot(string $space, string $id): array
    {
        $ids = array_keys($this->subtreeEntryIds($space, $id)); $marks = implode(',', array_fill(0, count($ids), '?'));
        $q = $this->pdo->prepare('SELECT id, revision FROM storage_entries WHERE space_id = ? AND id IN (' . $marks . ') ORDER BY id'); $q->execute([$space, ...$ids]);
        $snapshot = []; foreach ($q->fetchAll() as $row) { $snapshot[$row['id']] = (int) $row['revision']; }
        return $snapshot;
    }
    public function details(string $actor, string $space, string $id): array
    {
        $this->requireAccess($actor, 'files.list', $space); $row = $this->entry($space, $id); $this->parent($space, $row['parent_id']);
        if ($row['trashed_at'] !== null) { throw new \InvalidArgumentException('Entry is trashed.'); }
        return $row;
    }
    /** Exact active child lookup. Names are decoded by the transport once, never by this service. */
    public function child(string $actor, string $space, string $parent, string $name): ?array
    {
        $this->requireAccess($actor, 'files.list', $space); EntryName::validate($name);
        $this->space($space); $this->parent($space, $parent);
        $q = $this->pdo->prepare('SELECT * FROM storage_entries WHERE space_id = ? AND parent_id = ? AND name = ? AND trashed_at IS NULL');
        $q->execute([$space, $parent, $name]); $row = $q->fetch();
        // The database collation may be case insensitive. DAV paths are exact names.
        return $row !== false && $row['name'] === $name ? $row : null;
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
        string $expectedParent, ?string $name = null, string $parent = '', ?int $expectedRevision = null,
        ?string $replaceId = null, ?int $replaceRevision = null): string
    {
        $rights = ['rename' => ['files.update'], 'move' => ['files.update','files.create','files.delete'],
            'copy' => ['files.read','files.create'], 'trash' => ['files.delete'], 'restore' => ['files.restore','files.create']];
        if (!isset($rights[$operation])) { throw new \InvalidArgumentException('Unknown storage operation.'); }
        if ($replaceId !== null) {
            if (!in_array($operation, ['move','copy'], true) || $replaceId === $id || $replaceRevision === null) { throw new \InvalidArgumentException('Invalid overwrite.'); }
            $rights[$operation] = array_values(array_unique([...$rights[$operation], 'files.update', 'files.delete']));
        }
        if ($name !== null) { EntryName::validate($name); }
        $newId = $operation === 'copy' ? bin2hex(random_bytes(16)) : $id;
        $targets = ['entry' => $id, 'result' => $newId]; if ($replaceId !== null) { $targets['replaced'] = $replaceId; }
        $context = new MutationContext(bin2hex(random_bytes(16)), $actor,
            $operation === 'copy' ? 'storage.file.copy' : 'storage.entry.' . $operation, Scope::Space, $space,
            $targets, storageChange: new StorageChange($name, $parent));
        $guard = function () use ($actor, $space, $id, $operation, $rights, $expectedName, $expectedParent, $parent, $expectedRevision, $replaceId, $replaceRevision, $name): void {
            foreach ($rights[$operation] as $action) { $this->requireAccess($actor, $action, $space); }
            $row = $this->entry($space, $id);
            if ($row['name'] !== $expectedName || $row['parent_id'] !== $expectedParent) { throw new MutationConflict(); }
            if ($expectedRevision !== null && (int) $row['revision'] !== $expectedRevision) { throw new MutationConflict(); }
            if ($replaceId !== null) {
                $target = $this->entry($space, $replaceId);
                if ($target['trashed_at'] !== null || (int) $target['revision'] !== $replaceRevision || $target['parent_id'] !== $parent
                    || $target['name'] !== ($name ?? $row['name']) || $target['kind'] !== $row['kind']) { throw new MutationConflict(); }
                $this->parent($space, $target['parent_id']);
                $this->locks()->assertWritable($actor, $space, $replaceId, $this->proof(), $target['kind'] === 'folder', true);
                $replacedIds = $this->subtreeEntryIds($space, $replaceId);
                foreach ($this->locks()->conflicts($space, $replaceId, true) as $lease) {
                    if (isset($replacedIds[$lease['entry_id']])) { throw new StorageLocked('Locked destinations cannot be replaced.'); }
                }
                $ancestor = $row['parent_id']; $visited = 0;
                while ($ancestor !== '') {
                    if ($ancestor === $replaceId || ++$visited > 128) { throw new MutationConflict(); }
                    $ancestor = $this->entry($space, $ancestor)['parent_id'];
                }
            }
            if (($operation === 'restore') !== ($row['trashed_at'] !== null)) { throw new MutationConflict(); }
            $this->parent($space, $row['parent_id']);
            if ($operation !== 'copy') { $this->locks()->assertWritable($actor,$space,$id,$this->proof(),$row['kind'] === 'folder',true); }
            if (in_array($operation, ['move','copy'], true)) {
                $this->locks()->assertWritable($actor,$space,$parent,$this->proof());
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
            if ($operation === 'copy') { $this->assertCatalogSpace($space); }
        };
        $this->mutations->run($context, $guard, function () use ($space, $id, $newId, $operation, $name, $parent, $replaceId, $replaceRevision): bool {
            $row = $this->entry($space, $id);
            if ($operation === 'trash' || $operation === 'restore') {
                if ($operation === 'restore') {
                    $query = $this->pdo->prepare('SELECT 1 FROM storage_entries WHERE space_id = ? AND parent_id = ? AND name = ? AND trashed_at IS NULL AND id <> ?');
                    $query->execute([$space, $row['parent_id'], $row['name'], $id]);
                    if ($query->fetchColumn() !== false) { throw new MutationConflict(); }
                }
                $query = $this->pdo->prepare('UPDATE storage_entries SET trashed_at = ?, revision = revision + 1 WHERE id = ?');
                $query->execute([$operation === 'trash' ? time() : null, $id]);
                if ($operation === 'trash') {
                    $ids = array_keys($this->subtreeEntryIds($space, $id));
                    $delete = $this->pdo->prepare('DELETE FROM storage_locks WHERE space_id = ? AND entry_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
                    $delete->execute([$space, ...$ids]);
                }
                return true;
            }
            $newName = $name ?? $row['name']; $newParent = $operation === 'rename' ? $row['parent_id'] : $parent;
            if ($operation !== 'copy' && $newName === $row['name'] && $newParent === $row['parent_id']) { return false; }
            if ($replaceId !== null) {
                $query = $this->pdo->prepare('UPDATE storage_entries SET trashed_at = ?, revision = revision + 1 WHERE id = ? AND space_id = ? AND revision = ? AND trashed_at IS NULL');
                $query->execute([time(), $replaceId, $space, $replaceRevision]);
                if ($query->rowCount() !== 1) { throw new MutationConflict(); }
            }
            $query = $this->pdo->prepare('SELECT 1 FROM storage_entries WHERE space_id = ? AND parent_id = ? AND name = ? AND id <> ? AND trashed_at IS NULL');
            $query->execute([$space, $newParent, $newName, $newId]); if ($query->fetchColumn() !== false) { throw new MutationConflict(); }
            if ($operation === 'copy') {
                if (StorageUsage::bytes($this->pdo, $space) + (int) $row['size_bytes'] > (int) $this->space($space)['quota_bytes']) { throw new \LengthException('Quota exceeded.'); }
                // Immutable content can safely be shared; quota still counts the logical copy.
                $stream = $this->content->open($row['blob_id'], (int) $row['size_bytes']); fclose($stream);
                $query = $this->pdo->prepare("INSERT INTO storage_entries (id, space_id, parent_id, name, kind, blob_id, size_bytes, sha256, created_at) VALUES (?, ?, ?, ?, 'file', ?, ?, ?, ?)");
                $query->execute([$newId, $space, $newParent, $newName, $row['blob_id'], $row['size_bytes'], $row['sha256'], time()]);
            } else {
                $query = $this->pdo->prepare('UPDATE storage_entries SET name = ?, parent_id = ?, revision = revision + 1 WHERE id = ?'); $query->execute([$newName, $newParent, $id]);
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
    public function versions(string $actor, string $space, string $id, ?string $after = null): array
    {
        $this->requireAccess($actor, 'files.read', $space); $this->requireAccess($actor, 'files.versions.read', $space);
        $entry = $this->details($actor, $space, $id);
        if ($entry['kind'] !== 'file') { throw new \InvalidArgumentException('Not a file.'); }
        $before = $after === null ? PHP_INT_MAX : (int) $this->version($id, $after)['revision'];
        $q = $this->pdo->prepare('SELECT id, revision, size_bytes, created_at FROM storage_versions WHERE entry_id = ? AND revision < ? ORDER BY revision DESC LIMIT 101'); $q->execute([$id, $before]);
        $rows = $q->fetchAll(); $next = null; if (count($rows) > 100) { array_pop($rows); $next = $rows[99]['id']; }
        return ['entry' => $entry, 'versions' => $rows, 'next' => $next];
    }
    public function downloadVersion(string $actor, string $space, string $id, string $version): array
    {
        $this->requireAccess($actor, 'files.read', $space); $this->requireAccess($actor, 'files.versions.read', $space);
        $entry = $this->details($actor, $space, $id); $row = $this->version($id, $version);
        $row['name'] = $entry['name']; return ['row' => $row, 'stream' => $this->content->open($row['blob_id'], (int) $row['size_bytes'])];
    }
    /** Replace content without deleting any prior current state. @param resource $source */
    public function replace(string $actor, string $space, string $id, int $expectedRevision, $source, bool $requireRead = false): void
    {
        $this->requireAccess($actor, 'files.update', $space);
        if ($requireRead) { $this->requireAccess($actor, 'files.read', $space); }
        $row = $this->details($actor, $space, $id);
        if ($row['kind'] !== 'file' || (int) $row['revision'] !== $expectedRevision) { throw new MutationConflict(); }
        $this->locks()->assertWritable($actor,$space,$id,$this->proof());
        $blob = $this->content->publish($source);
        $this->publishVersion($actor, $space, $id, $expectedRevision, $row, $blob, false, $requireRead);
    }
    public function restoreVersion(string $actor, string $space, string $id, string $version, int $expectedRevision): void
    {
        $this->requireAccess($actor, 'files.update', $space); $this->requireAccess($actor, 'files.read', $space); $this->requireAccess($actor, 'files.versions.read', $space);
        $row = $this->details($actor, $space, $id); $old = $this->version($id, $version);
        $this->publishVersion($actor, $space, $id, $expectedRevision, $row,
            ['id' => $old['blob_id'], 'size' => (int) $old['size_bytes'], 'sha256' => $old['sha256']], true);
    }
    private function publishVersion(string $actor, string $space, string $id, int $expectedRevision, array $expected, array $blob, bool $restore, bool $requireRead = false): void
    {
        $version = bin2hex(random_bytes(16));
        $context = new MutationContext(bin2hex(random_bytes(16)), $actor, $restore ? 'storage.version.restore' : 'storage.file.replace', Scope::Space, $space,
            ['entry' => $id, 'version' => $version], storageChange: new StorageChange($expected['name'], $expected['parent_id'], $blob['size'], $blob['sha256']));
        $guard = function () use ($actor, $space, $id, $expectedRevision, $expected, $restore, $requireRead): void {
            $this->requireAccess($actor, 'files.update', $space);
            if ($requireRead) { $this->requireAccess($actor, 'files.read', $space); }
            if ($restore) { $this->requireAccess($actor, 'files.read', $space); $this->requireAccess($actor, 'files.versions.read', $space); }
            $row = $this->details($actor, $space, $id);
            $this->locks()->assertWritable($actor,$space,$id,$this->proof());
            if ($row['kind'] !== 'file' || (int) $row['revision'] !== $expectedRevision || $row['name'] !== $expected['name'] || $row['parent_id'] !== $expected['parent_id']) { throw new MutationConflict(); }
            $q = $this->pdo->prepare('SELECT COUNT(*) FROM storage_versions WHERE entry_id = ?'); $q->execute([$id]);
            if ((int) $q->fetchColumn() >= 1000) { throw new \LengthException('History capacity reached. No versions deleted.'); }
        };
        $this->mutations->run($context, $guard, function () use ($space, $id, $expectedRevision, $blob, $version): bool {
            if (StorageUsage::bytes($this->pdo, $space) + $blob['size'] > (int) $this->space($space)['quota_bytes']) { throw new \LengthException('History quota exceeded.'); }
            $stream = $this->content->open($blob['id'], $blob['size']); fclose($stream);
            $old = $this->entry($space, $id);
            $q = $this->pdo->prepare('INSERT INTO storage_versions (id, entry_id, revision, blob_id, size_bytes, sha256, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $q->execute([$version, $id, $old['revision'], $old['blob_id'], $old['size_bytes'], $old['sha256'], time()]);
            $q = $this->pdo->prepare('UPDATE storage_entries SET blob_id = ?, size_bytes = ?, sha256 = ?, revision = revision + 1 WHERE id = ? AND revision = ?');
            $q->execute([$blob['id'], $blob['size'], $blob['sha256'], $id, $expectedRevision]);
            if ($q->rowCount() !== 1) { throw new MutationConflict(); } return true;
        });
    }
    private function version(string $entry, string $version): array
    {
        $q = $this->pdo->prepare('SELECT * FROM storage_versions WHERE id = ? AND entry_id = ?'); $q->execute([$version, $entry]);
        $row = $q->fetch(); if ($row === false) { throw new \InvalidArgumentException('Unknown historical version.'); } return $row;
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
    /** Include retained descendants so a deleted subtree cannot keep stale protocol leases. */
    private function subtreeEntryIds(string $space, string $id): array
    {
        $q = $this->pdo->prepare('SELECT id, parent_id FROM storage_entries WHERE space_id = ? LIMIT 10001'); $q->execute([$space]);
        $rows = $q->fetchAll(); if (count($rows) > 10000) { throw new \LengthException('Storage catalog capacity reached.'); }
        $children = []; foreach ($rows as $row) { $children[$row['parent_id']][] = $row['id']; }
        $seen = []; $queue = [$id];
        while ($queue !== []) {
            $current = array_pop($queue); if (isset($seen[$current])) { throw new \InvalidArgumentException('Cyclic subtree.'); }
            $seen[$current] = true; foreach ($children[$current] ?? [] as $child) { $queue[] = $child; }
        }
        return $seen;
    }
}
