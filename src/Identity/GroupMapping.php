<?php
declare(strict_types=1);

namespace Samla\Core\Identity;

final readonly class GroupMapping
{
    public function __construct(public string $externalId, public string $internalId)
    {
        if ($externalId === '' || $internalId === '') { throw new \InvalidArgumentException('Group IDs must not be empty.'); }
    }
}
