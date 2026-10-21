<?php
declare(strict_types=1);
namespace Samla\Core\WebDav;
use Samla\Core\Identity\AppPasswordService;
use Samla\PluginApi\{AuthorizationChecker, AuthorizationRequest, AuthorizationResult};

/** Recheck credential revocation as part of every core resource authorization. */
final readonly class CredentialAuthorization implements AuthorizationChecker
{
    public function __construct(private AuthorizationChecker $core, private AppPasswordService $passwords, private RequestIdentity $identity) {}
    public function decide(AuthorizationRequest $request): AuthorizationResult
    {
        $id = $this->identity->credential();
        if ($id === null || $request->actorId !== $this->identity->actor()
            || !$this->passwords->isCredentialActive($request->actorId, $id)) { return AuthorizationResult::deny('Inactive DAV credential.'); }
        return $this->core->decide($request);
    }
}
