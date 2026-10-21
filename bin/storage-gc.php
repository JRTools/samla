<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
require dirname(__DIR__) . '/vendor/autoload.php';
use Samla\Core\Administration\PdoMutationRunner;
use Samla\Core\Configuration\AppConfig;
use Samla\Core\Database\ConnectionFactory;
use Samla\Core\Identity\{IdentityPlugin, PdoIdentityRepository};
use Samla\Core\Plugins\PluginRuntime;
use Samla\Core\Storage\{LocalContentStore, OrphanCollector, StoragePlugin};
use Samla\Plugins\Rbac\{PdoRoleRepository, RbacPlugin};
try {
    if (count($argv) > 2 || (isset($argv[1]) && $argv[1] !== '--apply')) { throw new \InvalidArgumentException(); }
    $config = AppConfig::fromGlobals(); if (!$config->maintenance) { throw new \RuntimeException(); }
    $pdo = (new ConnectionFactory())->connect($config->database); $identity = new PdoIdentityRepository($pdo, $config->groupMaxAgeSeconds);
    $runtime = PluginRuntime::boot([new IdentityPlugin(), new StoragePlugin(), new RbacPlugin(new PdoRoleRepository($pdo), $identity)]);
    $result = (new OrphanCollector($pdo, new LocalContentStore($config->storageRoot), new PdoMutationRunner($pdo, $runtime)))->collect(isset($argv[1]));
    fwrite(STDOUT, 'Old unreferenced candidates: ' . count($result['candidates']) . '; removed: ' . count($result['removed']) . ".\n");
    foreach ($result['candidates'] as $id) { fwrite(STDOUT, $id . "\n"); }
} catch (\Throwable) { fwrite(STDERR, "Collection failed. Stop all writers and enable maintenance. Default is dry-run; --apply removes up to 1000 unreferenced objects older than 24 hours.\n"); exit(1); }
