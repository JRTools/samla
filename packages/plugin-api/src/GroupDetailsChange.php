<?php
declare(strict_types=1);

namespace Samla\PluginApi;

/** Bounded validation proposal, excluded from identifier-only audit and outbox payloads. */
final readonly class GroupDetailsChange
{
    public function __construct(public string $name, public ?string $expectedName = null)
    {
        self::validateName($name);
        if ($expectedName !== null) { self::validateName($expectedName); }
    }
    private static function validateName(string $name): void
    {
        if ($name === '' || strlen($name) > 1020 || preg_match('//u', $name) !== 1
            || preg_match('/[\p{Cc}\p{Cs}]|\A\s|\s\z/u', $name) !== 0
            || preg_match_all('/./us', $name) > 255) {
            throw new \InvalidArgumentException('Group name must contain 1 to 255 Unicode characters without controls or surrounding whitespace.');
        }
    }
}
