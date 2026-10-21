<?php
declare(strict_types=1);

namespace Samla\PluginApi;

/** Mandatory delegation check in addition to central membership administration permission. */
interface GroupMembershipPolicy
{
    /** Evaluate every role assignment of the target group. Errors must abort the mutation. */
    public function allows(string $actorId, string $userId, string $groupId, bool $grant): bool;
}
