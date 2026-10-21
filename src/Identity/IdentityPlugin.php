<?php
declare(strict_types=1);

namespace Samla\Core\Identity;

use Samla\PluginApi\{Permission, Plugin, PluginRegistrar, Scope};

final class IdentityPlugin implements Plugin
{
    public function id(): string { return 'platform.identity'; }
    public function apiMajor(): int { return 0; }
    public function register(PluginRegistrar $registrar): void
    {
        $registrar->permission(new Permission('identity.users.status.manage', $this->id(), [Scope::Global]));
        $registrar->permission(new Permission('identity.groups.memberships.manage', $this->id(), [Scope::Global]));
        $registrar->permission(new Permission('identity.groups.details.manage', $this->id(), [Scope::Global]));
        $registrar->permission(new Permission('identity.groups.mappings.manage', $this->id(), [Scope::Global]));
    }
}
