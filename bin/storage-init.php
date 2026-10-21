<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
require dirname(__DIR__) . '/vendor/autoload.php';
use Samla\Core\Configuration\AppConfig;
use Samla\Core\Storage\LocalContentStore;
try {
    if (count($argv) > 2 || (isset($argv[1]) && !preg_match('/^[a-zA-Z_][a-zA-Z0-9_.-]{0,63}$/D', $argv[1]))) { throw new \InvalidArgumentException(); }
    $config = AppConfig::fromGlobals(); if (!$config->maintenance) { throw new \RuntimeException(); }
    $store = new LocalContentStore($config->storageRoot);
    // Do not create root-owned inaccessible parent directories on behalf of another account.
    if (!is_dir($store->root) && !mkdir($store->root, 0700)) { throw new \RuntimeException(); }
    if (!chmod($store->root, 0700) || (isset($argv[1]) && !chown($store->root, $argv[1]))) { throw new \RuntimeException(); }
    $path = $store->root . '/.check.' . bin2hex(random_bytes(16));
    $stream = fopen($path, 'xb'); if ($stream === false) { throw new \RuntimeException(); }
    try { if (fwrite($stream, 'samla') !== 5 || !fflush($stream)) { throw new \RuntimeException(); } }
    finally { fclose($stream); unlink($path); }
    fwrite(STDOUT, "Private storage initialized with mode 0700. Verify the PHP-FPM user owns and can traverse the directory.\n");
} catch (\Throwable) { fwrite(STDERR, "Storage initialization failed. Usage: APP_MAINTENANCE=1 php bin/storage-init.php [PHP_FPM_USERNAME]\nRun as the PHP-FPM user, or as root with its explicit username. Parent directories must be traversable.\n"); exit(1); }
