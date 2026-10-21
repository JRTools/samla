<?php
declare(strict_types=1);

namespace Samla\PluginApi;

interface IdentityReader
{
    public function isAccessAllowed(string $actorId): bool;
    /** @return list<string> Confirmed current internal group identifiers. */
    public function groupIds(string $actorId): array;
}
