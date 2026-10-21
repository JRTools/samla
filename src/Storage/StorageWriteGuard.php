<?php
declare(strict_types=1);
namespace Samla\Core\Storage;

/** Internal shared guard for every storage writer. Proof never replaces authorization. */
final readonly class StorageWriteGuard
{
    public function __construct(private \PDO $pdo, private ?\Closure $clock = null) {}
    public function now(): int { return $this->clock === null ? time() : ($this->clock)(); }
    public static function token(string $token): void
    { if (!preg_match('/^[a-zA-Z0-9_.:-]{16,128}$/D', $token)) { throw new \InvalidArgumentException('Invalid lock token.'); } }
    public function assertWritable(string $actor, string $space, string $entry, array $proof = [], bool $subtree = false, bool $membership = false): void
    {
        if (count($proof) > 32) { throw new \InvalidArgumentException('Too many lock tokens.'); }
        foreach ($proof as $token) { if (!is_string($token)) { throw new \InvalidArgumentException('Invalid proof.'); } self::token($token); }
        foreach ($this->conflicts($space, $entry, $subtree, $membership) as $lock) {
            $matches = false;
            foreach ($proof as $token) { $matches = hash_equals($lock['token'], $token) || $matches; }
            if ($lock['actor_id'] !== $actor || !$matches) { throw new StorageLocked('An active write lock prevents this change.'); }
        }
    }
    public function assertVisible(string $space, string $entry): void
    {
        $nodes = $this->nodes($space);
        foreach ([$entry, ...array_keys($this->ancestors($entry, $nodes))] as $id) {
            if ($id !== '' && $nodes[$id]['trashed_at'] !== null) { throw new \InvalidArgumentException('Locked target is not visible.'); }
        }
    }
    /** @return list<array> Existing locks overlapping a write or proposed lock extent. */
    public function conflicts(string $space, string $entry, bool $subtree = false, bool $membership = false): array
    {
        $nodes = $this->nodes($space); $ancestors = $this->ancestors($entry, $nodes);
        $parent = $entry === '' ? null : $nodes[$entry]['parent_id'];
        $q = $this->pdo->prepare('SELECT * FROM storage_locks WHERE space_id = ? AND expires_at > ? ORDER BY id LIMIT 1001'); $q->execute([$space,$this->now()]);
        $locks = $q->fetchAll(); if (count($locks) > 1000) { throw new \LengthException('Lock capacity reached.'); }
        return array_values(array_filter($locks, function (array $lock) use ($entry, $parent, $ancestors, $nodes, $subtree, $membership): bool {
            $target = $lock['entry_id'];
            return $target === $entry || ((bool)$lock['recursive_lock'] && isset($ancestors[$target]))
                || ($membership && $target === $parent)
                || ($subtree && isset($this->ancestors($target,$nodes)[$entry]));
        }));
    }
    private function nodes(string $space): array
    {
        $q = $this->pdo->prepare('SELECT 1 FROM storage_spaces WHERE id = ?'); $q->execute([$space]);
        if ($q->fetchColumn() === false) { throw new \InvalidArgumentException('Unknown storage space.'); }
        $q = $this->pdo->prepare('SELECT id, parent_id, kind, trashed_at FROM storage_entries WHERE space_id = ? LIMIT 10001'); $q->execute([$space]);
        $rows = $q->fetchAll(); if (count($rows) > 10000) { throw new \LengthException('Space catalog exceeds the lock guard capacity.'); }
        return array_column($rows,null,'id');
    }
    private function ancestors(string $entry, array $nodes): array
    {
        $parents = []; $seen = [];
        while ($entry !== '') {
            if (!isset($nodes[$entry]) || isset($seen[$entry]) || count($seen) > 128) { throw new \InvalidArgumentException('Invalid lock ancestry.'); }
            $seen[$entry] = true; $parent = $nodes[$entry]['parent_id'];
            if ($parent !== '' && (!isset($nodes[$parent]) || $nodes[$parent]['kind'] !== 'folder')) { throw new \InvalidArgumentException('Invalid lock parent.'); }
            $parents[$parent] = true; $entry = $parent;
        }
        return $parents;
    }
}
