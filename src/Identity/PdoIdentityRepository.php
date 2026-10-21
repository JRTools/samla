<?php
declare(strict_types=1);

namespace Samla\Core\Identity;

use Samla\Core\Database\Transaction;
use Samla\PluginApi\{GroupDetailsChange, GroupMappingChange, IdentityReader, MutationConflict};

/** Internal persistence adapter. Mutations are not authorized administration endpoints. */
final readonly class PdoIdentityRepository implements IdentityReader
{
    public function __construct(private \PDO $pdo, private ?int $groupMaxAgeSeconds, private ?\Closure $clock = null)
    {
        if ($groupMaxAgeSeconds !== null && $groupMaxAgeSeconds < 1) { throw new \InvalidArgumentException('Invalid group freshness.'); }
    }
    private function now(): int { return $this->clock === null ? time() : ($this->clock)(); }
    public function isAccessAllowed(string $actorId): bool
    {
        if ($this->groupMaxAgeSeconds === null) { return false; }
        $now = $this->now();
        $query = $this->pdo->prepare("SELECT 1 FROM identity_users WHERE id = ? AND status = 'active' AND groups_confirmed_at BETWEEN ? AND ?");
        $query->execute([$actorId, $now - $this->groupMaxAgeSeconds, $now]);
        return $query->fetchColumn() !== false;
    }
    public function groupIds(string $actorId): array
    {
        if ($this->groupMaxAgeSeconds === null) { return []; }
        $now = $this->now();
        $query = $this->pdo->prepare("SELECT DISTINCT m.group_id FROM identity_memberships m JOIN identity_users u ON u.id = m.user_id
            WHERE u.id = ? AND u.status = 'active' AND u.groups_confirmed_at BETWEEN ? AND ? ORDER BY m.group_id");
        $query->execute([$actorId, $now - $this->groupMaxAgeSeconds, $now]);
        return $query->fetchAll(\PDO::FETCH_COLUMN);
    }
    public function createUser(string $id, string $issuer, string $subject, string $displayName): void
    {
        self::id($id);
        if ($issuer === '' || strlen($issuer) > 1024 || $subject === '' || strlen($subject) > 255) { throw new \InvalidArgumentException('Invalid identity.'); }
        $query = $this->pdo->prepare('INSERT INTO identity_users (id, issuer, subject, display_name) VALUES (?, ?, ?, ?)');
        $query->execute([$id, $issuer, $subject, $displayName]);
    }
    public function createGroup(string $id, string $name): void
    {
        self::id($id);
        new GroupDetailsChange($name);
        $query = $this->pdo->prepare('INSERT INTO identity_groups (id, name) VALUES (?, ?)'); $query->execute([$id, $name]);
    }
    /** Internal writer. The application owns authorization, administration lock and continuity checks. */
    public function changeGroupDetailsInsideTransaction(string $id, GroupDetailsChange $change): bool
    {
        if (!$this->pdo->inTransaction()) { throw new \LogicException('An active transaction is required.'); }
        self::id($id);
        $query = $this->pdo->prepare('SELECT name FROM identity_groups WHERE id = ? FOR UPDATE');
        $query->execute([$id]); $current = $query->fetchColumn();
        if ($change->expectedName === null) {
            if ($current !== false) { throw new MutationConflict('Group identifier is already in use.'); }
            $this->createGroup($id, $change->name);
            return true;
        }
        if ($current === false) { throw new \InvalidArgumentException('Unknown group.'); }
        if ($current !== $change->expectedName) { throw new MutationConflict('Group name has changed.'); }
        if ($current === $change->name) { return false; }
        $query = $this->pdo->prepare('UPDATE identity_groups SET name = ? WHERE id = ?');
        $query->execute([$change->name, $id]);
        return true;
    }
    public function setBlocked(string $id, bool $blocked): void
    {
        Transaction::run($this->pdo, fn(): bool => $this->setBlockedInsideTransaction($id, $blocked));
    }
    /** Internal writer for an authorized mutation that already owns the administration lock. */
    public function setBlockedInsideTransaction(string $id, bool $blocked): bool
    {
        if (!$this->pdo->inTransaction()) { throw new \LogicException('An active transaction is required.'); }
        $this->lockUser($id);
        $status = $blocked ? 'blocked' : 'active';
        $query = $this->pdo->prepare('UPDATE identity_users SET status = ?, authorization_version = authorization_version + 1 WHERE id = ? AND status <> ?');
        $query->execute([$status, $id, $status]);
        return $query->rowCount() !== 0;
    }
    /** Internal candidate directory; effective authorization must still be checked for each user. */
    public function userIds(): array
    {
        return $this->pdo->query('SELECT id FROM identity_users ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN);
    }
    public function addManualMembership(string $userId, string $groupId): void
    {
        Transaction::run($this->pdo, fn(): bool => $this->setManualMembershipInsideTransaction($userId, $groupId, true));
    }
    /** Internal writer. Never deletes external membership provenance or refreshes verified claims. */
    public function setManualMembershipInsideTransaction(string $userId, string $groupId, bool $grant): bool
    {
        if (!$this->pdo->inTransaction()) { throw new \LogicException('An active transaction is required.'); }
        $this->lockUser($userId);
        $query = $this->pdo->prepare("SELECT 1 FROM identity_memberships WHERE user_id = ? AND group_id = ? AND origin = 'manual'");
        $query->execute([$userId, $groupId]);
        $exists = $query->fetchColumn() !== false;
        if ($exists === $grant) { return false; }
        $query = $this->pdo->prepare($grant
            ? "INSERT INTO identity_memberships (user_id, group_id, origin, source_group) VALUES (?, ?, 'manual', '')"
            : "DELETE FROM identity_memberships WHERE user_id = ? AND group_id = ? AND origin = 'manual'");
        $query->execute([$userId, $groupId]);
        $this->bump($userId);
        return true;
    }
    /** Internal mapping writer; updates only this external source, retaining snapshot age and local status. */
    public function changeGroupMappingInsideTransaction(GroupMappingChange $change): bool
    {
        if (!$this->pdo->inTransaction()) { throw new \LogicException('An active transaction is required.'); }
        $params = [$change->issuer, $change->externalId, $change->groupId];
        $query = $this->pdo->prepare('SELECT 1 FROM identity_group_mappings WHERE issuer = ? AND external_id = ? AND internal_id = ? FOR UPDATE');
        $query->execute($params); $exists = $query->fetchColumn() !== false;
        $changed = $exists !== $change->grant;
        if ($changed) {
            $query = $this->pdo->prepare($change->grant
                ? 'INSERT INTO identity_group_mappings (issuer, external_id, internal_id) VALUES (?, ?, ?)'
                : 'DELETE FROM identity_group_mappings WHERE issuer = ? AND external_id = ? AND internal_id = ?');
            $query->execute($params);
        }
        // Include source rows without a corresponding current snapshot to repair drift as well.
        $query = $this->pdo->prepare("SELECT u.id FROM identity_users u WHERE u.issuer = ? AND (
            EXISTS (SELECT 1 FROM identity_external_groups e WHERE e.user_id = u.id AND e.external_id = ?)
            OR EXISTS (SELECT 1 FROM identity_memberships m WHERE m.user_id = u.id AND m.group_id = ? AND m.origin = 'external' AND m.source_group = ?))
            ORDER BY u.id FOR UPDATE");
        $query->execute([$change->issuer, $change->externalId, $change->groupId, $change->externalId]);
        $users = $query->fetchAll(\PDO::FETCH_COLUMN);
        $snapshot = $this->pdo->prepare('SELECT 1 FROM identity_external_groups WHERE user_id = ? AND external_id = ?');
        $membership = $this->pdo->prepare("SELECT 1 FROM identity_memberships WHERE user_id = ? AND group_id = ? AND origin = 'external' AND source_group = ?");
        $insert = $this->pdo->prepare("INSERT INTO identity_memberships (user_id, group_id, origin, source_group) VALUES (?, ?, 'external', ?)");
        $delete = $this->pdo->prepare("DELETE FROM identity_memberships WHERE user_id = ? AND group_id = ? AND origin = 'external' AND source_group = ?");
        foreach ($users as $userId) {
            $snapshot->execute([$userId, $change->externalId]);
            $wanted = $change->grant && $snapshot->fetchColumn() !== false;
            $membership->execute([$userId, $change->groupId, $change->externalId]);
            if (($membership->fetchColumn() !== false) === $wanted) { continue; }
            ($wanted ? $insert : $delete)->execute([$userId, $change->groupId, $change->externalId]);
            $this->bump($userId); $changed = true;
        }
        return $changed;
    }
    /** Only supply a claim already verified for this stored issuer/subject. */
    public function replaceVerifiedGroups(string $userId, mixed $claim): void
    {
        Transaction::run($this->pdo, fn() => $this->replaceVerifiedGroupsInsideTransaction($userId, $claim));
    }
    /** Internal trusted login writer; caller must own its transaction. */
    public function replaceVerifiedGroupsInsideTransaction(string $userId, mixed $claim): void
    {
        if (!$this->pdo->inTransaction()) { throw new \LogicException('An active transaction is required.'); }
        $lock = $this->pdo->query('SELECT id FROM administration_lock WHERE id = 1 FOR UPDATE');
        if ($lock->fetchColumn() === false) { throw new \RuntimeException('Administration lock is missing.'); }
        $user = $this->lockUser($userId);
        $query = $this->pdo->prepare('SELECT external_id, internal_id FROM identity_group_mappings WHERE issuer = ?');
        $query->execute([$user['issuer']]);
        $mappings = [];
        foreach ($query as $row) { $mappings[] = new GroupMapping($row['external_id'], $row['internal_id']); }
        $derived = (new GroupMapper())->mapVerifiedClaim($claim, $mappings);
        $delete = $this->pdo->prepare("DELETE FROM identity_memberships WHERE user_id = ? AND origin = 'external'"); $delete->execute([$userId]);
        $delete = $this->pdo->prepare('DELETE FROM identity_external_groups WHERE user_id = ?'); $delete->execute([$userId]);
        $insert = $this->pdo->prepare('INSERT INTO identity_external_groups (user_id, external_id) VALUES (?, ?)');
        foreach (array_unique($claim, SORT_STRING) as $external) { $insert->execute([$userId, $external]); }
        $insert = $this->pdo->prepare("INSERT INTO identity_memberships (user_id, group_id, origin, source_group) VALUES (?, ?, 'external', ?)");
        foreach ($derived as $membership) {
            foreach ($membership->externalSources as $source) { $insert->execute([$userId, $membership->internalId, $source]); }
        }
        $update = $this->pdo->prepare('UPDATE identity_users SET groups_confirmed_at = ?, authorization_version = authorization_version + 1 WHERE id = ?');
        $update->execute([$this->now(), $userId]);
    }
    private function lockUser(string $id): array
    {
        $query = $this->pdo->prepare('SELECT issuer FROM identity_users WHERE id = ? FOR UPDATE'); $query->execute([$id]);
        $user = $query->fetch();
        if ($user === false) { throw new \InvalidArgumentException('Unknown user.'); }
        return $user;
    }
    private function bump(string $id): void
    {
        $query = $this->pdo->prepare('UPDATE identity_users SET authorization_version = authorization_version + 1 WHERE id = ?'); $query->execute([$id]);
    }
    private static function id(string $id): void
    {
        if (!preg_match('/^[a-zA-Z0-9_.:-]{1,64}$/D', $id)) { throw new \InvalidArgumentException('Invalid internal ID.'); }
    }
}
