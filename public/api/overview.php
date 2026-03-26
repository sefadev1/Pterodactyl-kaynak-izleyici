<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use App\HistoryStore;
use App\HttpClient;
use App\MetricsAggregator;
use App\PterodactylService;

try {
    $service = new PterodactylService(new HttpClient());
    $overview = $service->fetchOverview();
    $history = (new HistoryStore())->append($overview);
    $payload = (new MetricsAggregator())->build($overview, $history);

    json_response([
        'ok' => true,
        'app' => [
            'name' => app_config('app.name'),
            'refresh_seconds' => app_config('app.refresh_seconds'),
            'demo_mode' => app_config('pterodactyl.demo_mode'),
        ],
        'data' => $payload,
    ]);
} catch (Throwable $throwable) {
    json_response([
        'ok' => false,
        'message' => $throwable->getMessage(),
    ], 500);
}
