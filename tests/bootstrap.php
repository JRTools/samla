<?php
declare(strict_types=1);

$prefixes = [
    'Samla\\Core\\' => __DIR__ . '/../src/',
    'Samla\\PluginApi\\' => __DIR__ . '/../packages/plugin-api/src/',
    'Samla\\Plugins\\Rbac\\' => __DIR__ . '/../plugins/rbac/src/',
    'Samla\\Tests\\' => __DIR__ . '/',
];
spl_autoload_register(static function (string $class) use ($prefixes): void {
    foreach ($prefixes as $prefix => $directory) {
        if (str_starts_with($class, $prefix)) {
            $path = $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($path)) { require $path; }
            return;
        }
    }
});
