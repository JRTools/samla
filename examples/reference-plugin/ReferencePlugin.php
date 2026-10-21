<?php
declare(strict_types=1);

namespace Samla\Examples;

use Samla\PluginApi\{AccessRestriction, AuthorizationRequest, AuthorizationResult, Decision, Permission, Plugin, PluginRegistrar, Scope};

final class ReferencePlugin implements Plugin
{
    public function id(): string { return 'example.reference'; }
    public function apiMajor(): int { return 0; }
    public function register(PluginRegistrar $registrar): void
    {
        $registrar->permission(new Permission('example.inspect', $this->id(), [Scope::Space]));
        $registrar->restriction(new class implements AccessRestriction {
            public function decide(AuthorizationRequest $request): AuthorizationResult {
                return new AuthorizationResult(Decision::NotApplicable, 'no_additional_restriction');
            }
        });
    }
}
