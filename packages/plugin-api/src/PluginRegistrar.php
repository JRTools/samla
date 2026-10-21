<?php
declare(strict_types=1);

namespace Samla\PluginApi;

interface PluginRegistrar
{
    public function permission(Permission $permission): void;
    public function authorizationProvider(AuthorizationProvider $provider): void;
    public function restriction(AccessRestriction $restriction, int $priority = 0): void;
}
