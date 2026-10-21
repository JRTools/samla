<?php
declare(strict_types=1);

namespace Samla\Core\Identity;

use Samla\PluginApi\{AccessDenied, AuthorizationChecker, AuthorizationRequest, Decision, Scope};

/** Internal postcondition. Evaluate resulting state inside the shared administration transaction. */
final readonly class AdministrationAccessGuard
{
    public function __construct(private PdoIdentityRepository $identities, private AuthorizationChecker $authorization) {}

    public function assertAvailable(string $action): void
    {
        if (!$this->hasAvailable($action)) { throw new AccessDenied(); }
    }
    public function hasAvailable(string $action): bool
    {
        foreach ($this->identities->userIds() as $candidate) {
            if ($this->authorization->decide(new AuthorizationRequest($candidate,
                $action, Scope::Global))->decision === Decision::Permit) { return true; }
        }
        return false;
    }
}
