<?php
declare(strict_types=1);

namespace Samla\Plugins\Rbac;

interface RoleRepository
{
    public function find(string $roleId): ?Role;
    /** @param list<string> $groupIds @return iterable<RoleAssignment> */
    public function assignmentsFor(array $groupIds): iterable;
}
