<?php
declare(strict_types=1);
// Router for the explicitly local PHP development server. Never expose arbitrary project files.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$assets = ['/assets/samla.css' => 'text/css', '/assets/workspace.js' => 'text/javascript'];
if (isset($assets[$path])) {
    header('Content-Type: ' . $assets[$path] . '; charset=utf-8'); header('X-Content-Type-Options: nosniff');
    readfile(__DIR__ . $path); return;
}
require __DIR__ . '/index.php';

