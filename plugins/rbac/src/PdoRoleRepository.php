<?php
declare(strict_types=1);

namespace Samla\Plugins\Rbac;

use Samla\PluginApi\{PermissionRegistry, Scope};

/** Private plugin adapter; public services must perform authorization, delegation and audit before exposing writes. */
final readonly class PdoRoleRepository implements RoleRepository
{
    public function __construct(private \PDO $pdo) {}
    public function find(string $roleId): ?Role
    {
        $query = $this->pdo->prepare('SELECT id, scope FROM rbac_roles WHERE id = ?'); $query->execute([$roleId]);
        $row = $query->fetch();
        if ($row === false) { return null; }
        $query = $this->pdo->prepare('SELECT permission_id FROM rbac_role_permissions WHERE role_id = ? ORDER BY permission_id'); $query->execute([$roleId]);
        return new Role($row['id'], Scope::from($row['scope']), $query->fetchAll(\PDO::FETCH_COLUMN));
    }
    public function assignmentsFor(array $groupIds): iterable
    {
        if ($groupIds === []) { return []; }
        // Bound query count and placeholder count; no IDs are interpolated into SQL.
        if (count($groupIds) > 1000) { throw new \InvalidArgumentException('Too many groups.'); }
        $query = $this->pdo->prepare('SELECT group_id, role_id, scope, space_id FROM rbac_assignments WHERE group_id IN (' . implode(',', array_fill(0, count($groupIds), '?')) . ') ORDER BY group_id, role_id, scope, space_id');
        $query->execute(array_values($groupIds));
        $result = [];
        foreach ($query as $row) {
            $result[] = new RoleAssignment($row['group_id'], $row['role_id'], Scope::from($row['scope']), $row['scope'] === 'global' ? null : $row['space_id']);
        }
        return $result;
    }
    public function save(Role $role, PermissionRegistry $registry): void
    {
        $this->transaction(fn() => $this->saveInsideTransaction($role, $registry));
    }
    /** Trusted setup writer, not an authorized public role-management endpoint. */
    public function saveInsideTransaction(Role $role, PermissionRegistry $registry): void
    {
        if (!$this->pdo->inTransaction()) { throw new \LogicException('An active transaction is required.'); }
        self::id($role->id);
        foreach ($role->permissions as $action) {
            $permission = $registry->find($action);
            if ($permission === null || !in_array($role->scope, $permission->scopes, true)) { throw new \InvalidArgumentException('Unknown permission or invalid role scope.'); }
        }
        $query = $this->pdo->prepare('SELECT scope FROM rbac_roles WHERE id = ? FOR UPDATE'); $query->execute([$role->id]);
        $existing = $query->fetchColumn();
        if ($existing === false) {
            $query = $this->pdo->prepare('INSERT INTO rbac_roles (id, scope) VALUES (?, ?)'); $query->execute([$role->id, $role->scope->value]);
        } else {
            if ($existing !== $role->scope->value) {
                $query = $this->pdo->prepare('SELECT 1 FROM rbac_assignments WHERE role_id = ? LIMIT 1'); $query->execute([$role->id]);
                if ($query->fetchColumn() !== false) { throw new \LogicException('Cannot change scope of an assigned role.'); }
            }
            $query = $this->pdo->prepare('UPDATE rbac_roles SET scope = ? WHERE id = ?'); $query->execute([$role->scope->value, $role->id]);
        }
        $query = $this->pdo->prepare('DELETE FROM rbac_role_permissions WHERE role_id = ?'); $query->execute([$role->id]);
        $query = $this->pdo->prepare('INSERT INTO rbac_role_permissions (role_id, permission_id) VALUES (?, ?)');
        foreach (array_unique($role->permissions) as $action) { $query->execute([$role->id, $action]); }
    }
    public function assign(RoleAssignment $assignment): void
    {
        $this->transaction(fn() => $this->assignInsideTransaction($assignment));
    }
    /** Internal writer used only inside an authorized mutation transaction. */
    public function assignInsideTransaction(RoleAssignment $assignment): bool
    {
        $this->lockAssignmentRole($assignment);
        $params = [$assignment->groupId, $assignment->roleId, $assignment->scope->value, $assignment->spaceId ?? ''];
        $query = $this->pdo->prepare('SELECT 1 FROM rbac_assignments WHERE group_id = ? AND role_id = ? AND scope = ? AND space_id = ?'); $query->execute($params);
        if ($query->fetchColumn() !== false) { return false; }
        $query = $this->pdo->prepare('INSERT INTO rbac_assignments (group_id, role_id, scope, space_id) VALUES (?, ?, ?, ?)'); $query->execute($params);
        return true;
    }
    public function revokeInsideTransaction(RoleAssignment $assignment): bool
    {
        $this->lockAssignmentRole($assignment);
        $query = $this->pdo->prepare('DELETE FROM rbac_assignments WHERE group_id = ? AND role_id = ? AND scope = ? AND space_id = ?');
        $query->execute([$assignment->groupId, $assignment->roleId, $assignment->scope->value, $assignment->spaceId ?? '']);
        return $query->rowCount() === 1;
    }
    private function lockAssignmentRole(RoleAssignment $assignment): void
    {
        if (!$this->pdo->inTransaction()) { throw new \LogicException('Assignment writer requires a transaction.'); }
        self::id($assignment->groupId);
        if ($assignment->spaceId !== null) { self::id($assignment->spaceId); }
        $query = $this->pdo->prepare('SELECT scope FROM rbac_roles WHERE id = ? FOR UPDATE'); $query->execute([$assignment->roleId]);
        if ($query->fetchColumn() !== $assignment->scope->value) { throw new \InvalidArgumentException('Role scope does not match assignment.'); }
    }
    /** An explicit matching rule is always needed. A grant additionally respects its frozen permission ceiling. */
    public function canDelegate(array $groups, RoleAssignment $assignment, PermissionRegistry $registry, bool $grant): bool
    {
        if ($groups === [] || count($groups) > 1000 || $assignment->scope !== Scope::Space) { return false; }
        $role = $this->find($assignment->roleId);
        if ($role === null || $role->scope !== Scope::Space) { return false; }
        // This first service cannot alter administration-bearing assignments or remove its own administrative foundation.
        foreach ($role->permissions as $action) { if (str_starts_with($action, 'rbac.')) { return false; } }
        $query = $this->pdo->prepare('SELECT grantor_group_id FROM rbac_delegations WHERE role_id = ? AND space_id = ? AND grantor_group_id IN (' . implode(',', array_fill(0, count($groups), '?')) . ')');
        $query->execute([$assignment->roleId, $assignment->spaceId, ...array_values($groups)]);
        $rules = $query->fetchAll(\PDO::FETCH_COLUMN);
        if (!$grant) { return $rules !== []; }
        foreach ($role->permissions as $action) {
            $permission = $registry->find($action);
            if ($permission === null || !in_array(Scope::Space, $permission->scopes, true)) { return false; }
        }
        foreach ($rules as $group) {
            $ceiling = $this->pdo->prepare('SELECT permission_id FROM rbac_delegation_permissions WHERE grantor_group_id = ? AND role_id = ? AND space_id = ?');
            $ceiling->execute([$group, $assignment->roleId, $assignment->spaceId]);
            if (array_diff($role->permissions, $ceiling->fetchAll(\PDO::FETCH_COLUMN)) === []) { return true; }
        }
        return false;
    }
    private function transaction(\Closure $operation): void
    {
        if ($this->pdo->inTransaction()) { throw new \LogicException('Nested plugin transactions are not supported.'); }
        $this->pdo->beginTransaction();
        try { $operation(); $this->pdo->commit(); }
        catch (\Throwable $error) { if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); } throw $error; }
    }
    private static function id(string $id): void
    {
        if (!preg_match('/^[a-zA-Z0-9_.:-]{1,64}$/D', $id)) { throw new \InvalidArgumentException('Invalid internal ID.'); }
    }
}
