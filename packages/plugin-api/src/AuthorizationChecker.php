<?php
declare(strict_types=1);

namespace Samla\PluginApi;

/** Complete core authorization, not an individual policy provider. */
interface AuthorizationChecker
{
    public function decide(AuthorizationRequest $request): AuthorizationResult;
}
