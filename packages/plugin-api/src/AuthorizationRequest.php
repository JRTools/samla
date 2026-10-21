<?php
declare(strict_types=1);

namespace Samla\PluginApi;

final readonly class AuthorizationRequest
{
    public function __construct(
        public string $actorId,
        public string $action,
        public Scope $scope,
        public ?string $spaceId = null,
        public ?string $resourceId = null,
    ) {
        if ($actorId === '' || $action === '') { throw new \InvalidArgumentException('Actor and action are required.'); }
        if (($scope === Scope::Space && ($spaceId === null || $spaceId === '')) || ($scope === Scope::Global && $spaceId !== null)) {
            throw new \InvalidArgumentException('Scope and space do not match.');
        }
    }
}
