<?php
declare(strict_types=1);

namespace Samla\PluginApi;

interface AuthorizationProvider
{
    public function decide(AuthorizationRequest $request): AuthorizationResult;
}
