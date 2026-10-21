<?php
declare(strict_types=1);

namespace Samla\Tests\Fixtures;
use Samla\PluginApi\IdentityReader;
final class Identities implements IdentityReader
{
    public bool $allowed = true;
    public array $groups = ['editors'];
    public function isAccessAllowed(string $actorId): bool { return $this->allowed; }
    public function groupIds(string $actorId): array { return $this->groups; }
}
