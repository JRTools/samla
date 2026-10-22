<?php
declare(strict_types=1);
namespace Samla\PluginApi;

/** Guarded administration facade. Core UI never reads an authorization provider's private tables. */
interface AccessProfileAdministration
{
    public function canManage(string $actorId): bool;
    /** @return list<array{profile:AccessProfile,assigned:bool,available:bool,fingerprint:string}> */
    public function state(string $actorId, string $groupId, ?string $spaceId): array;
    public function change(string $actorId, string $profileId, string $groupId, ?string $spaceId,
        bool $grant, string $expectedState): bool;
}
