<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'Ptero Resource Watch',
        'timezone' => 'Europe/Istanbul',
        'refresh_seconds' => 15,
        'history_limit' => 120,
    ],
    'pterodactyl' => [
        'panel_url' => 'https://panel.example.com',
        'client_api_key' => 'ptlc_example_client_key',
        'application_api_key' => 'ptla_example_application_key',
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
];
