<?php
declare(strict_types=1);

namespace Samla\Core\Storage;

use Samla\PluginApi\{Permission, Plugin, PluginRegistrar, Scope};

final class StoragePlugin implements Plugin
{
    public function id(): string { return 'platform.storage'; }
    public function apiMajor(): int { return 0; }
    public function register(PluginRegistrar $registrar): void
    {
        // Every file operation requires an explicit assignment in its own space.
        foreach (['list', 'read', 'create', 'update', 'delete', 'restore', 'purge'] as $action) {
            $registrar->permission(new Permission('files.' . $action, $this->id(), [Scope::Space]));
        }
        $registrar->permission(new Permission('files.versions.read', $this->id(), [Scope::Space]));
    }
}
