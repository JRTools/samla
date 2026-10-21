<?php
declare(strict_types=1);

namespace Samla\Plugins\Rbac;

use Samla\PluginApi\{AuthorizationChecker, AuthorizationRequest, Decision, GroupMembershipPolicy, IdentityReader, PermissionRegistry, ResourceDirectory, Scope};

/** Manual membership conveys every assignment of a group, including assignments in other spaces. */
final readonly class RbacGroupMembershipPolicy implements GroupMembershipPolicy
{
    public function __construct(private PdoRoleRepository $roles, private IdentityReader $identities,
        private AuthorizationChecker $authorization, private PermissionRegistry $permissions,
        private ResourceDirectory $resources) {}

    public function allows(string $actorId, string $userId, string $groupId, bool $grant): bool
    {
        $groups = $this->identities->groupIds($actorId);
        foreach ($this->roles->assignmentsFor([$groupId]) as $assignment) {
            // Global and administration-bearing groups remain outside this first delegation contract.
            if ($assignment->scope !== Scope::Space
                || !$this->resources->hasSpace($assignment->spaceId)
                || $this->authorization->decide(new AuthorizationRequest($actorId, 'rbac.assignments.manage',
                    Scope::Space, $assignment->spaceId))->decision !== Decision::Permit
                || !$this->roles->canDelegate($groups, $assignment, $this->permissions, $grant)) {
                return false;
            }
        }
        return true;
    }
}
