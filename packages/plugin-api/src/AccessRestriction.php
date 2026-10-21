<?php
declare(strict_types=1);

namespace Samla\PluginApi;

interface AccessRestriction
{
    /** Additional restrictions may deny, but cannot replace a mandatory permit. */
    public function decide(AuthorizationRequest $request): AuthorizationResult;
}
