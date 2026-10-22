<?php
declare(strict_types=1);

$checks = [
    'php_8_4_or_8_5' => PHP_VERSION_ID >= 80400 && PHP_VERSION_ID < 80600,
    'pdo' => extension_loaded('pdo'),
    'pdo_mysql' => extension_loaded('pdo_mysql'),
    'session' => extension_loaded('session'),
    'openssl' => extension_loaded('openssl'),
    'iconv' => extension_loaded('iconv'),
    'mbstring' => extension_loaded('mbstring'),
    'https_streams' => in_array('https', stream_get_wrappers(), true),
    'allow_url_fopen' => filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL),
    'gd' => extension_loaded('gd'),
    'exif' => extension_loaded('exif'),
    'iptcparse' => function_exists('iptcparse'),
    'dom' => class_exists(DOMDocument::class),
    'fileinfo' => extension_loaded('fileinfo'),
];
foreach ($checks as $name => $ok) { echo ($ok ? '[OK] ' : '[MISSING] ') . $name . PHP_EOL; }
echo 'Connectivity, OIDC registration, storage permissions and background processing need separate verification.' . PHP_EOL;
exit(in_array(false, $checks, true) ? 1 : 0);
