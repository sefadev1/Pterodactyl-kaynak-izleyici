<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$target = __DIR__ . '/public' . $path;

if ($path !== '/' && is_file($target)) {
    $extension = strtolower(pathinfo($target, PATHINFO_EXTENSION));
    $mimeTypes = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'html' => 'text/html; charset=utf-8',
        'php' => 'text/html; charset=utf-8',
    ];

    if ($extension === 'php') {
        require $target;
        return;
    }

    header('Content-Type: ' . ($mimeTypes[$extension] ?? 'application/octet-stream'));
    readfile($target);
    return;
}

require __DIR__ . '/public/index.php';
