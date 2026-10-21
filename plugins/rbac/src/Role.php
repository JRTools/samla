<?php
declare(strict_types=1);

namespace Samla\Plugins\Rbac;

use Samla\PluginApi\Scope;

final readonly class Role
{
    /** @param list<string> $permissions */
    public function __construct(public string $id, public Scope $scope, public array $permissions)
    {
        if ($id === '') { throw new \InvalidArgumentException('Role ID is required.'); }
        foreach ($permissions as $permission) {
            if (!is_string($permission) || !preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/D', $permission)) {
                throw new \InvalidArgumentException('Explicit permission IDs are required.');
            }
        }
    }
}
