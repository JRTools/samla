<?php
declare(strict_types=1);

namespace Samla\PluginApi;

interface PermissionRegistry
{
    public function register(Permission $permission): void;
    public function find(string $id): ?Permission;
}
