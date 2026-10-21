<?php
declare(strict_types=1);
// Isolated integration helper, never a production command.
require dirname(__DIR__, 2) . '/vendor/autoload.php';
use Samla\Core\Administration\PdoMutationRunner;
use Samla\Core\Authorization\AuthorizationService;
use Samla\Core\Configuration\AppConfig;
use Samla\Core\Database\ConnectionFactory;
use Samla\Core\Identity\{IdentityPlugin, PdoIdentityRepository};
use Samla\Core\Plugins\PluginRuntime;
use Samla\Core\Storage\{FileService, LocalContentStore, StoragePlugin};
use Samla\Plugins\Rbac\{PdoRoleRepository, RbacPlugin};
try {
    $config = AppConfig::fromGlobals(); if ($config->environment !== 'test' || count($argv) !== 3) { throw new \RuntimeException(); }
    [, $root, $worker] = $argv;
    if (!preg_match('/^[0-4]$/D', $worker) || !str_starts_with($root, sys_get_temp_dir() . '/samla-files-')) { throw new \RuntimeException(); }
    $pdo = (new ConnectionFactory())->connect($config->database); $identity = new PdoIdentityRepository($pdo, 3600);
    $runtime = PluginRuntime::boot([new IdentityPlugin(), new StoragePlugin(), new RbacPlugin(new PdoRoleRepository($pdo), $identity)]);
    $service = new FileService($pdo, new AuthorizationService($runtime, $identity), new PdoMutationRunner($pdo, $runtime), new LocalContentStore($root));
    file_put_contents($root . '/ready.' . $worker, 'ready'); $deadline = microtime(true) + 15;
    while (!is_file($root . '/release')) { if (microtime(true) > $deadline) { throw new \RuntimeException(); } usleep(10000); }
    $stream = fopen('php://temp', 'w+b'); fwrite($stream, '1234567890'); rewind($stream);
    try { $service->upload('alice', 'a', '', 'worker.' . $worker, $stream); echo "created\n"; }
    catch (\LengthException) { echo "quota\n"; }
    finally { fclose($stream); }
} catch (\Throwable) { fwrite(STDERR, "Isolated concurrency worker failed.\n"); exit(1); }
