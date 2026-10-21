<?php
declare(strict_types=1);

namespace Samla\Plugins\Rbac;

use Samla\PluginApi\{IdentityReader, Permission, Plugin, PluginRegistrar, Scope};

final readonly class RbacPlugin implements Plugin
{
    public function __construct(private RoleRepository $roles, private IdentityReader $identities) {}
    public function id(): string { return 'platform.rbac'; }
    public function apiMajor(): int { return 0; }
    public function register(PluginRegistrar $registrar): void
    {
        $registrar->permission(new Permission('rbac.roles.manage', $this->id(), [Scope::Global]));
        $registrar->permission(new Permission('rbac.assignments.manage', $this->id(), [Scope::Global, Scope::Space]));
        $registrar->authorizationProvider(new RbacProvider($this->roles, $this->identities));
    }
}
