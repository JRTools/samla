<?php
declare(strict_types=1);

namespace Samla\Plugins\Rbac;

use Samla\PluginApi\{GroupMappingChange, GroupMappingPolicy};

final readonly class RbacGroupMappingPolicy implements GroupMappingPolicy
{
    public function __construct(private RbacGroupMembershipPolicy $delegation) {}
    public function allows(string $actorId, GroupMappingChange $change): bool
    { return $this->delegation->allowsGroup($actorId, $change->groupId, $change->grant); }
}
