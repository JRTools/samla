<?php
declare(strict_types=1);
// Router for the explicitly local PHP development server. Never expose arbitrary project files.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($path === '/assets/samla.css') {
    header('Content-Type: text/css; charset=utf-8'); header('X-Content-Type-Options: nosniff');
    readfile(__DIR__ . '/assets/samla.css'); return;
}
require __DIR__ . '/index.php';
