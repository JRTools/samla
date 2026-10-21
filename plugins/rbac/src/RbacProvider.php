<?php
declare(strict_types=1);

namespace Samla\Plugins\Rbac;

use Samla\PluginApi\{AuthorizationProvider, AuthorizationRequest, AuthorizationResult, Decision, IdentityReader};

final readonly class RbacProvider implements AuthorizationProvider
{
    public function __construct(private RoleRepository $roles, private IdentityReader $identities) {}
    public function decide(AuthorizationRequest $request): AuthorizationResult
    {
        $groups = $this->identities->groupIds($request->actorId);
        foreach ($this->roles->assignmentsFor($groups) as $assignment) {
            // Repository output is not sufficient to bypass membership and scope checks.
            if (!in_array($assignment->groupId, $groups, true) || $assignment->scope !== $request->scope || $assignment->spaceId !== $request->spaceId) { continue; }
            $role = $this->roles->find($assignment->roleId);
            if ($role !== null && $role->scope === $request->scope && in_array($request->action, $role->permissions, true)) {
                return new AuthorizationResult(Decision::Permit, 'role_assignment');
            }
        }
        return AuthorizationResult::deny('no_matching_assignment');
    }
}
