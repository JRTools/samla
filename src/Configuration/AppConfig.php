<?php
declare(strict_types=1);

namespace Samla\Core\Configuration;

final readonly class AppConfig
{
    private function __construct(
        public string $environment,
        public DatabaseConfig $database,
        public bool $maintenance,
        public ?int $groupMaxAgeSeconds,
        public string $storageRoot,
        public int $maxUploadBytes,
        public ExportConfig $exports,
    ) {}

    /** Trusted .env and process values only; never request headers or query parameters. */
    public static function fromGlobals(): self { return self::fromEnvironment(EnvironmentLoader::fromGlobals()); }

    /** @param array<string, string> $env */
    public static function fromEnvironment(#[\SensitiveParameter] array $env): self
    {
        $environment = $env['APP_ENV'] ?? 'production';
        if (!in_array($environment, ['development', 'test', 'production'], true)) { throw new \InvalidArgumentException('Invalid APP_ENV.'); }
        $prefix = $environment === 'test' ? 'TEST_DB_' : 'DB_';
        $required = static function (string $key) use ($env): string {
            if (!array_key_exists($key, $env) || !is_string($env[$key])) { throw new \InvalidArgumentException('Missing configuration: ' . $key); }
            return $env[$key];
        };
        $port = $env[$prefix . 'PORT'] ?? '3306';
        if (!ctype_digit($port)) { throw new \InvalidArgumentException('Invalid database port.'); }
        $database = new DatabaseConfig(
            $required($prefix . 'ENGINE'), $env[$prefix . 'HOST'] ?? '127.0.0.1', (int) $port,
            $required($prefix . 'NAME'), $required($prefix . 'USER'), $required($prefix . 'PASSWORD'),
            ($env[$prefix . 'SOCKET'] ?? '') !== '' ? $env[$prefix . 'SOCKET'] : null,
        );
        if ($environment === 'test') {
            if (!str_starts_with($database->database, 'media_platform_test_')
                || (isset($env['DB_NAME']) && $env['DB_NAME'] === $database->database)) {
                throw new \InvalidArgumentException('Tests require a separate media_platform_test_* database.');
            }
        }
        $age = $env['APP_GROUP_MAX_AGE_SECONDS'] ?? '';
        if ($age !== '' && (!ctype_digit($age) || (int) $age < 1 || (int) $age > 2147483647)) {
            throw new \InvalidArgumentException('Invalid group freshness setting.');
        }
        $maintenance = $env['APP_MAINTENANCE'] ?? '0';
        if (!in_array($maintenance, ['0', '1'], true)) { throw new \InvalidArgumentException('Invalid maintenance flag.'); }
        $root = $env['APP_STORAGE_ROOT'] ?? dirname(__DIR__, 2) . '/var/storage';
        if (!is_string($root) || !str_starts_with($root, '/') || str_contains($root, "\0")) { throw new \InvalidArgumentException('Invalid storage root.'); }
        $max = $env['APP_MAX_UPLOAD_BYTES'] ?? '16777216';
        if (!is_string($max) || !ctype_digit($max) || strlen($max) > 13 || (int) $max < 1 || (int) $max > 1099511627776) { throw new \InvalidArgumentException('Invalid upload limit.'); }
        return new self($environment, $database, $maintenance === '1', $age === '' ? null : (int) $age, $root, (int) $max,ExportConfig::fromEnvironment($env));
    }
}
