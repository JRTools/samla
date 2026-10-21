<?php
declare(strict_types=1);

namespace Samla\PluginApi;

final readonly class Permission
{
    /** @param list<Scope> $scopes */
    public function __construct(public string $id, public string $ownerPlugin, public array $scopes)
    {
        if (!preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/D', $id) || $ownerPlugin === '' || $scopes === []) {
            throw new \InvalidArgumentException('Invalid permission definition.');
        }
        foreach ($scopes as $scope) {
            if (!$scope instanceof Scope) { throw new \InvalidArgumentException('Invalid permission scope.'); }
        }
    }
}
