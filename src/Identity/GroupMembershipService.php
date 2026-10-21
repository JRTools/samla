<?php
declare(strict_types=1);

namespace Samla\Core\Identity;

use Samla\PluginApi\{AccessDenied, AuthorizationChecker, AuthorizationRequest, Decision, GroupMembershipPolicy, MutationContext, MutationRunner, ResourceDirectory, Scope};

/** Public manual membership use case. Compose with RBAC policy and the shared mutation runner. */
final readonly class GroupMembershipService
{
    public function __construct(private PdoIdentityRepository $identities, private AuthorizationChecker $authorization,
        private GroupMembershipPolicy $policy, private ResourceDirectory $resources, private MutationRunner $mutations) {}

    public function grant(string $actorId, string $userId, string $groupId): bool
    { return $this->change($actorId, $userId, $groupId, true); }
    public function revoke(string $actorId, string $userId, string $groupId): bool
    { return $this->change($actorId, $userId, $groupId, false); }

    private function change(string $actorId, string $userId, string $groupId, bool $grant): bool
    {
        $context = new MutationContext(bin2hex(random_bytes(16)), $actorId,
            $grant ? 'identity.membership.grant' : 'identity.membership.revoke', Scope::Global, null,
            ['user' => $userId, 'group' => $groupId]);
        $continuity = new AdministrationAccessGuard($this->identities, $this->authorization);
        $protected = null;
        $guard = function () use ($actorId, $userId, $groupId, $grant, $continuity, &$protected): void {
            if ($this->authorization->decide(new AuthorizationRequest($actorId,
                    'identity.groups.memberships.manage', Scope::Global))->decision !== Decision::Permit
                || !$this->resources->hasGroup($groupId)
                || !$this->policy->allows($actorId, $userId, $groupId, $grant)) { throw new AccessDenied(); }
            $protected ??= $continuity->snapshot();
        };
        return $this->mutations->run($context, $guard, function () use ($userId, $groupId, $grant, $continuity, &$protected): bool {
            $changed = $this->identities->setManualMembershipInsideTransaction($userId, $groupId, $grant);
            $continuity->assertAvailable('identity.users.status.manage');
            $continuity->assertPreserved($protected);
            return $changed;
        });
    }
}
