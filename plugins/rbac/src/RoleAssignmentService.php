<?php
declare(strict_types=1);

namespace Samla\Plugins\Rbac;

use Samla\PluginApi\{AccessDenied, AuthorizationChecker, AuthorizationRequest, Decision, IdentityReader, MutationContext, MutationRunner, PermissionRegistry, ResourceDirectory, Scope};

/** First public administration use case. Global assignments are deliberately outside this contract. */
final readonly class RoleAssignmentService
{
    public function __construct(
        private PdoRoleRepository $roles,
        private IdentityReader $identities,
        private AuthorizationChecker $authorization,
        private PermissionRegistry $permissions,
        private ResourceDirectory $resources,
        private MutationRunner $mutations,
    ) {}
    public function grant(string $actorId, RoleAssignment $assignment): bool { return $this->change($actorId, $assignment, true); }
    public function revoke(string $actorId, RoleAssignment $assignment): bool { return $this->change($actorId, $assignment, false); }
    private function change(string $actorId, RoleAssignment $assignment, bool $grant): bool
    {
        if ($assignment->scope !== Scope::Space) { throw new AccessDenied(); }
        $context = new MutationContext(bin2hex(random_bytes(16)), $actorId, $grant ? 'rbac.assignment.grant' : 'rbac.assignment.revoke',
            Scope::Space, $assignment->spaceId, ['group' => $assignment->groupId, 'role' => $assignment->roleId]);
        $guard = function () use ($actorId, $assignment, $grant): void {
            $result = $this->authorization->decide(new AuthorizationRequest($actorId, 'rbac.assignments.manage', Scope::Space, $assignment->spaceId));
            if ($result->decision !== Decision::Permit
                || !$this->resources->hasGroup($assignment->groupId) || !$this->resources->hasSpace($assignment->spaceId)
                || !$this->roles->canDelegate($this->identities->groupIds($actorId), $assignment, $this->permissions, $grant)) {
                throw new AccessDenied();
            }
        };
        return $this->mutations->run($context, $guard, fn(): bool => $grant
            ? $this->roles->assignInsideTransaction($assignment) : $this->roles->revokeInsideTransaction($assignment));
    }
}
