<?php
declare(strict_types=1);

namespace Samla\Plugins\Rbac;

use Samla\PluginApi\Scope;

final readonly class RoleAssignment
{
    public function __construct(public string $groupId, public string $roleId, public Scope $scope, public ?string $spaceId = null)
    {
        if ($groupId === '' || $roleId === '' || ($scope === Scope::Space && ($spaceId === null || $spaceId === '')) || ($scope === Scope::Global && $spaceId !== null)) {
            throw new \InvalidArgumentException('Invalid role assignment.');
        }
    }
}
