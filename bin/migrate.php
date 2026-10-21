<?php
declare(strict_types=1);

// CLI only. No web route installs or executes arbitrary migration packages.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../vendor/autoload.php';

use Samla\Core\Configuration\AppConfig;
use Samla\Core\Database\{ConnectionFactory, MigrationRunner};

try {
    $config = AppConfig::fromGlobals();
    if (!$config->maintenance) { throw new \RuntimeException('Set APP_MAINTENANCE=1 before migration.'); }
    $pdo = (new ConnectionFactory())->connect($config->database);
    $migrations = require __DIR__ . '/../bootstrap/migrations.php';
    foreach ((new MigrationRunner($pdo))->migrate($migrations) as $id) { echo 'Applied: ' . $id . PHP_EOL; }
    echo 'Schema is current.' . PHP_EOL;
} catch (\Throwable) {
    fwrite(STDERR, 'Configuration, connection or migration failed. Check configuration and schema_migrations; do not retry unfinished migrations without inspection.' . PHP_EOL);
    exit(1);
}
