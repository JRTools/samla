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
        // Capabilities are declarations only; file operations are not implemented yet.
        foreach (['list', 'read', 'create', 'update'] as $action) {
            $registrar->permission(new Permission('files.' . $action, $this->id(), [Scope::Space]));
        }
    }
}
