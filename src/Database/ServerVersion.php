<?php
declare(strict_types=1);

namespace Samla\Core\Database;

final class ServerVersion
{
    public static function assertSupported(string $engine, string $version): void
    {
        $valid = match ($engine) {
            'mariadb' => preg_match('/^(?:5\.5\.5-)?11\.8\.\d+(?:[-+.]|$)/', $version) === 1 && stripos($version, 'MariaDB') !== false,
            'mysql' => preg_match('/^8\.0\.\d+(?:[-+.]|$)/', $version) === 1 && stripos($version, 'MariaDB') === false,
            default => false,
        };
        if (!$valid) { throw new \RuntimeException('Database server does not match the configured supported release family.'); }
    }
}
