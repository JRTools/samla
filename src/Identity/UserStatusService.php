<?php
declare(strict_types=1);

namespace Samla\Core\Identity;

use Samla\PluginApi\{AccessDenied, AuthorizationChecker, AuthorizationRequest, Decision, MutationContext, MutationRunner, Scope};

/** Application entry point. Compose with the complete core checker and shared mutation runner. */
final readonly class UserStatusService
{
    public function __construct(private PdoIdentityRepository $identities,
        private AuthorizationChecker $authorization, private MutationRunner $mutations) {}

    public function setBlocked(string $actorId, string $userId, bool $blocked): bool
    {
        $context = new MutationContext(bin2hex(random_bytes(16)), $actorId,
            $blocked ? 'identity.user.block' : 'identity.user.unblock', Scope::Global, null, ['user' => $userId]);
        $guard = function () use ($actorId): void {
            if (!$this->canManageStatus($actorId)) { throw new AccessDenied(); }
        };
        return $this->mutations->run($context, $guard, function () use ($userId, $blocked): bool {
            $continuity = new AdministrationAccessGuard($this->identities, $this->authorization);
            $hadMembershipAdministrator = $continuity->hasAvailable('identity.groups.memberships.manage');
            $changed = $this->identities->setBlockedInsideTransaction($userId, $blocked);
            $continuity->assertAvailable('identity.users.status.manage');
            if ($hadMembershipAdministrator) { $continuity->assertAvailable('identity.groups.memberships.manage'); }
            return $changed;
        });
    }
    private function canManageStatus(string $userId): bool
    {
        return $this->authorization->decide(new AuthorizationRequest($userId,
            'identity.users.status.manage', Scope::Global))->decision === Decision::Permit;
    }
}
