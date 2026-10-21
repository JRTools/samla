<?php
declare(strict_types=1);

$checks = [
    'php_8_4_or_8_5' => PHP_VERSION_ID >= 80400 && PHP_VERSION_ID < 80600,
    'pdo' => extension_loaded('pdo'),
    'pdo_mysql' => extension_loaded('pdo_mysql'),
    'session' => extension_loaded('session'),
    'gd' => extension_loaded('gd'),
    'fileinfo' => extension_loaded('fileinfo'),
];
foreach ($checks as $name => $ok) { echo ($ok ? '[OK] ' : '[MISSING] ') . $name . PHP_EOL; }
echo 'Database connection, OIDC, storage permissions and background processing: not configured.' . PHP_EOL;
exit(in_array(false, $checks, true) ? 1 : 0);
