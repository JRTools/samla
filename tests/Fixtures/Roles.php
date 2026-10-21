<?php
declare(strict_types=1);

namespace Samla\Tests\Fixtures;
use Samla\Plugins\Rbac\{Role, RoleAssignment, RoleRepository};
final class Roles implements RoleRepository
{
    public array $roles = [];
    public array $assignments = [];
    public function find(string $roleId): ?Role { return $this->roles[$roleId] ?? null; }
    public function assignmentsFor(array $groupIds): iterable { return $this->assignments; }
}
