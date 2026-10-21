<?php
declare(strict_types=1);

namespace Samla\PluginApi;

interface ResourceDirectory
{
    public function hasGroup(string $id): bool;
    public function hasSpace(string $id): bool;
}
