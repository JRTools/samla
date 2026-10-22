<?php
declare(strict_types=1);
namespace Samla\PluginApi;

/** Exact server-selected profile and observed assignment state, available to change validators. */
final readonly class AccessProfileChange
{
    public function __construct(public AccessProfile $profile, public string $groupId, public ?string $spaceId,
        public bool $grant, public string $expectedState)
    {
        if (!preg_match('/^[a-zA-Z0-9_.:-]{1,64}$/D', $groupId)
            || !preg_match('/^[a-f0-9]{64}$/D', $expectedState)
            || ($profile->scope === Scope::Global && $spaceId !== null)
            || ($profile->scope === Scope::Space && ($spaceId === null || !preg_match('/^[a-zA-Z0-9_.:-]{1,64}$/D', $spaceId)))) {
            throw new \InvalidArgumentException('Invalid access profile change.');
        }
    }
}
