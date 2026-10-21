<?php
declare(strict_types=1);

namespace Samla\PluginApi;

/** Mandatory target-group delegation for present and future members, independent of current member count. */
interface GroupMappingPolicy
{
    public function allows(string $actorId, GroupMappingChange $change): bool;
}
