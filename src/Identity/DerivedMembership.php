<?php
declare(strict_types=1);

namespace Samla\Core\Identity;

final readonly class DerivedMembership
{
    /** @param list<string> $externalSources */
    public function __construct(public string $internalId, public array $externalSources, public bool $manual) {}
}
