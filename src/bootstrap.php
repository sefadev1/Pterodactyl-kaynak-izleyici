<?php

declare(strict_types=1);

date_default_timezone_set('Europe/Istanbul');

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

function app_config(?string $key = null, mixed $default = null): mixed
{
    static $config;

    if ($config === null) {
        $defaultConfig = [
            'app' => [
                'name' => 'Ptero Resource Watch',
                'timezone' => 'Europe/Istanbul',
                'refresh_seconds' => 15,
                'history_limit' => 120,
            ],
            'pterodactyl' => [
                'panel_url' => '',
                'client_api_key' => '',
                'application_api_key' => '',
                'verify_ssl' => true,
                'timeout_seconds' => 10,
                'demo_mode' => true,
            ],
            'thresholds' => [
                'cpu_warning' => 70,
                'cpu_critical' => 90,
                'memory_warning' => 70,
                'memory_critical' => 90,
                'disk_warning' => 75,
                'disk_critical' => 90,
            ],
            'storage' => [
                'history_file' => dirname(__DIR__) . '/storage/history.json',
            ],
        ];

        $examplePath = dirname(__DIR__) . '/config.example.php';
        if (is_file($examplePath)) {
            $loadedExample = require $examplePath;
            if (is_array($loadedExample)) {
                $defaultConfig = array_replace_recursive($defaultConfig, $loadedExample);
            }
        }

        $customPath = dirname(__DIR__) . '/config.php';
        $config = is_file($customPath) ? array_replace_recursive($defaultConfig, require $customPath) : $defaultConfig;
        date_default_timezone_set((string) ($config['app']['timezone'] ?? 'Europe/Istanbul'));
    }

    if ($key === null) {
        return $config;
    }

    $segments = explode('.', $key);
    $value = $config;

    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }

        $value = $value[$segment];
    }

    return $value;
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
