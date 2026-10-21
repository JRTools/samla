<?php
declare(strict_types=1);

namespace Samla\Core\Authorization;

use Samla\Core\Plugins\PluginRuntime;
use Samla\PluginApi\{AuthorizationRequest, AuthorizationResult, Decision, IdentityReader};

final readonly class AuthorizationService
{
    public function __construct(private PluginRuntime $runtime, private IdentityReader $identities) {}

    public function decide(AuthorizationRequest $request): AuthorizationResult
    {
        try {
            if (!$this->identities->isAccessAllowed($request->actorId)) { return AuthorizationResult::deny('identity_unavailable'); }
            $permission = $this->runtime->find($request->action);
            if ($permission === null || !in_array($request->scope, $permission->scopes, true)) {
                return AuthorizationResult::deny('unknown_or_invalid_action');
            }
            $provider = $this->runtime->provider();
            if ($provider === null) { return AuthorizationResult::deny('mandatory_provider_missing'); }
            $result = $provider->decide($request);
            if ($result->decision !== Decision::Permit) { return AuthorizationResult::deny('mandatory_provider_denied'); }
            foreach ($this->runtime->restrictions() as $restriction) {
                if ($restriction->decide($request)->decision === Decision::Deny) { return AuthorizationResult::deny('additional_restriction'); }
            }
            return new AuthorizationResult(Decision::Permit, 'authorized');
        } catch (\Throwable) {
            // No exception text, tokens or plugin internals escape in an authorization response.
            return AuthorizationResult::deny('authorization_failed');
        }
    }
}
