<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
require dirname(__DIR__) . '/vendor/autoload.php';
use Samla\Core\Configuration\AppConfig;
use Samla\Core\Database\ConnectionFactory;
use Samla\Core\Storage\LocalContentStore;

try {
    $config = AppConfig::fromGlobals();
    if (!$config->maintenance) { throw new \RuntimeException(); }
    $store = new LocalContentStore($config->storageRoot);
    if (!is_dir($store->root)) { throw new \RuntimeException(); }
    $pdo = (new ConnectionFactory())->connect($config->database);
    $referenced = []; $errors = 0;
    foreach ($pdo->query("SELECT blob_id, size_bytes, sha256 FROM storage_entries WHERE kind = 'file' UNION SELECT blob_id, size_bytes, sha256 FROM storage_versions") as $row) {
        $referenced[$row['blob_id']] = true;
        try {
            $stream = $store->open($row['blob_id'], (int) $row['size_bytes']);
            try { $hash = hash_init('sha256'); hash_update_stream($hash, $stream); $valid = hash_equals($row['sha256'], hash_final($hash)); }
            finally { fclose($stream); }
            if (!$valid) { throw new \RuntimeException(); }
        } catch (\Throwable) { ++$errors; fwrite(STDOUT, 'Missing or damaged object: ' . $row['blob_id'] . "\n"); }
    }
    foreach (new \DirectoryIterator($store->root) as $file) {
        if ($file->isDot()) { continue; }
        $name = $file->getFilename();
        if (preg_match('/^[a-f0-9]{64}$/D', $name) && !$file->isLink() && $file->isFile() && isset($referenced[$name])) { continue; }
        ++$errors;
        // Only server-generated identifier formats are exposed. No arbitrary filesystem names.
        fwrite(STDOUT, preg_match('/^(?:\.stage\.)?[a-f0-9]{64}$/D', $name) ? "Unreferenced object: $name\n" : "Unexpected storage entry.\n");
    }
    fwrite(STDOUT, 'Checked ' . count($referenced) . " referenced objects; findings: $errors. No files changed.\n");
    exit($errors === 0 ? 0 : 2);
} catch (\Throwable) { fwrite(STDERR, "Storage check failed. Require maintenance, migrated schema and readable private storage.\n"); exit(1); }
