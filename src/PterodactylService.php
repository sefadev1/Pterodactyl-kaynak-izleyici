<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

final class PterodactylService
{
    public function __construct(private readonly HttpClient $httpClient)
    {
    }

    public function fetchOverview(): array
    {
        if ((bool) app_config('pterodactyl.demo_mode', true)) {
            return $this->fakeOverview();
        }

        $panelUrl = rtrim((string) app_config('pterodactyl.panel_url', ''), '/');
        $applicationKey = (string) app_config('pterodactyl.application_api_key', '');

        if ($panelUrl === '' || $applicationKey === '') {
            throw new RuntimeException('panel_url and application_api_key must be configured.');
        }

        $applicationHeaders = $this->buildHeaders($applicationKey);
        $clientIndex = $this->fetchClientResourceIndex();
        $applicationServers = $this->fetchApplicationServers($panelUrl, $applicationHeaders);
        $nodeInventory = $this->fetchNodeInventory($panelUrl, $applicationHeaders);

        $servers = [];
        foreach ($applicationServers as $identifier => $server) {
            $resource = $clientIndex[$identifier] ?? null;
            $servers[] = [
                'uuid' => $server['uuid'],
                'identifier' => $identifier,
                'name' => $server['name'],
                'description' => $server['description'],
                'node' => $server['node'],
                'owner' => $server['owner'],
                'type' => $server['type'],
                'status' => $resource['status'] ?? 'untracked',
                'limits' => $server['limits'],
                'current' => $resource['current'] ?? [
                    'cpu_absolute' => 0.0,
                    'memory_bytes' => 0.0,
                    'disk_bytes' => 0.0,
                    'network_rx_bytes' => 0.0,
                    'network_tx_bytes' => 0.0,
                    'uptime' => 0,
                ],
            ];
        }

        return [
            'servers' => $servers,
            'node_inventory' => $nodeInventory,
        ];
    }

    private function fetchApplicationServers(string $panelUrl, array $headers): array
    {
        $items = $this->fetchPaginated($panelUrl . '/api/application/servers?per_page=100&include=node,user,nest,egg', $headers);
        $servers = [];

        foreach ($items as $entry) {
            $attributes = $entry['attributes'] ?? [];
            $identifier = (string) ($attributes['identifier'] ?? '');
            if ($identifier === '') {
                continue;
            }

            $relations = $attributes['relationships'] ?? $entry['relationships'] ?? [];
            $nodeValue = $this->resolveRelationshipName($relations['node']['attributes'] ?? null, $attributes['node'] ?? null, ['name', 'fqdn', 'id']);
            $ownerValue = $this->resolveRelationshipName($relations['user']['attributes'] ?? null, $attributes['user'] ?? null, ['username', 'email', 'id']);
            $nestValue = $this->resolveRelationshipName($relations['nest']['attributes'] ?? null, null, ['name', 'id']);
            $eggValue = $this->resolveRelationshipName($relations['egg']['attributes'] ?? null, null, ['name', 'id']);
            $dockerImage = (string) ($attributes['image'] ?? '');
            $startup = (string) ($attributes['startup'] ?? '');
            [$typeLabel, $typeFamily] = $this->detectServerType($nestValue, $eggValue, $dockerImage, $startup);

            $limits = $attributes['limits'] ?? $attributes['container'] ?? [];

            $servers[$identifier] = [
                'uuid' => (string) ($attributes['uuid'] ?? ''),
                'name' => (string) ($attributes['name'] ?? 'Unknown'),
                'description' => (string) ($attributes['description'] ?? ''),
                'node' => (string) $nodeValue,
                'owner' => (string) $ownerValue,
                'type' => [
                    'label' => $typeLabel,
                    'family' => $typeFamily,
                    'runtime' => $dockerImage !== '' ? $dockerImage : 'Unknown',
                    'nest' => (string) $nestValue,
                    'egg' => (string) $eggValue,
                ],
                'limits' => [
                    'cpu' => (float) ($limits['cpu'] ?? 0),
                    'memory' => (float) ($limits['memory'] ?? 0),
                    'disk' => (float) ($limits['disk'] ?? 0),
                ],
            ];
        }

        return $servers;
    }

    private function fetchClientResourceIndex(): array
    {
        $panelUrl = rtrim((string) app_config('pterodactyl.panel_url', ''), '/');
        $clientKey = (string) app_config('pterodactyl.client_api_key', '');

        if ($panelUrl === '' || $clientKey === '') {
            return [];
        }

        $headers = $this->buildHeaders($clientKey);
        $index = [];

        try {
            $servers = $this->fetchPaginated($panelUrl . '/api/client?per_page=100', $headers);
        } catch (\Throwable) {
            return [];
        }

        foreach ($servers as $entry) {
            $attributes = $entry['attributes'] ?? [];
            $identifier = (string) ($attributes['identifier'] ?? '');
            if ($identifier === '') {
                continue;
            }

            try {
                $resources = $this->httpClient->getJson(
                    $panelUrl . '/api/client/servers/' . $identifier . '/resources',
                    $headers,
                    (int) app_config('pterodactyl.timeout_seconds', 10),
                    (bool) app_config('pterodactyl.verify_ssl', true)
                );
                $serverStats = $resources['attributes']['resources'] ?? [];
                $currentState = (string) ($resources['attributes']['current_state'] ?? 'unknown');
            } catch (\Throwable $exception) {
                $serverStats = [];
                $currentState = $this->mapResourceFailureToState($exception->getMessage());
            }

            $index[$identifier] = [
                'status' => $currentState,
                'current' => [
                    'cpu_absolute' => (float) ($serverStats['cpu_absolute'] ?? $serverStats['cpu_percent'] ?? 0),
                    'memory_bytes' => (float) ($serverStats['memory_bytes'] ?? 0),
                    'disk_bytes' => (float) ($serverStats['disk_bytes'] ?? 0),
                    'network_rx_bytes' => (float) ($serverStats['network_rx_bytes'] ?? 0),
                    'network_tx_bytes' => (float) ($serverStats['network_tx_bytes'] ?? 0),
                    'uptime' => (int) ($serverStats['uptime'] ?? 0),
                ],
            ];
        }

        return $index;
    }

    private function fetchNodeInventory(string $panelUrl, array $headers): array
    {
        $items = $this->fetchPaginated($panelUrl . '/api/application/nodes?per_page=100', $headers);
        $nodes = [];

        foreach ($items as $entry) {
            $attributes = $entry['attributes'] ?? [];
            $name = (string) ($attributes['name'] ?? $attributes['fqdn'] ?? '');
            if ($name === '') {
                continue;
            }

            $nodes[$name] = [
                'name' => $name,
                'fqdn' => (string) ($attributes['fqdn'] ?? ''),
                'memory_mb' => (float) ($attributes['memory'] ?? 0),
                'disk_mb' => (float) ($attributes['disk'] ?? 0),
                'cpu_limit' => (float) ($attributes['cpu'] ?? 0),
                'server_count' => 0,
            ];
        }

        return $nodes;
    }

    private function fetchPaginated(string $baseUrl, array $headers): array
    {
        $results = [];
        $page = 1;
        $hasMore = true;

        while ($hasMore) {
            $separator = str_contains($baseUrl, '?') ? '&' : '?';
            $url = $baseUrl . $separator . 'page=' . $page;
            $response = $this->httpClient->getJson(
                $url,
                $headers,
                (int) app_config('pterodactyl.timeout_seconds', 10),
                (bool) app_config('pterodactyl.verify_ssl', true)
            );

            foreach (($response['data'] ?? []) as $item) {
                $results[] = $item;
            }

            $pagination = $response['meta']['pagination'] ?? null;
            if (!is_array($pagination)) {
                break;
            }

            $currentPage = (int) ($pagination['current_page'] ?? $page);
            $totalPages = (int) ($pagination['total_pages'] ?? $currentPage);
            $hasMore = $currentPage < $totalPages;
            $page++;
        }

        return $results;
    }

    private function buildHeaders(string $token): array
    {
        return [
            'Accept: Application/vnd.pterodactyl.v1+json',
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ];
    }

    private function resolveRelationshipName(mixed $relationshipAttributes, mixed $fallback, array $keys): string
    {
        $candidates = [];
        if (is_array($relationshipAttributes)) {
            $candidates[] = $relationshipAttributes;
        }

        if (is_array($fallback)) {
            $candidates[] = $fallback;
        }

        foreach ($candidates as $candidate) {
            foreach ($keys as $key) {
                if (isset($candidate[$key]) && $candidate[$key] !== '') {
                    return (string) $candidate[$key];
                }
            }
        }

        if ($fallback !== null && !is_array($fallback) && $fallback !== '') {
            return (string) $fallback;
        }

        return 'Unknown';
    }

    private function detectServerType(string $nest, string $egg, string $dockerImage, string $startup): array
    {
        $haystack = strtolower(trim($nest . ' ' . $egg . ' ' . $dockerImage . ' ' . $startup));
        $map = [
            ['minecraft', 'Minecraft', 'Oyun Sunucusu'],
            ['spigot', 'Minecraft Spigot', 'Oyun Sunucusu'],
            ['paper', 'Minecraft Paper', 'Oyun Sunucusu'],
            ['forge', 'Minecraft Forge', 'Oyun Sunucusu'],
            ['bungeecord', 'Minecraft BungeeCord', 'Proxy'],
            ['waterfall', 'Minecraft Waterfall', 'Proxy'],
            ['velocity', 'Minecraft Velocity', 'Proxy'],
            ['nodejs', 'Node.js Bot', 'Bot'],
            ['node.js', 'Node.js Bot', 'Bot'],
            ['python', 'Python Uygulamasi', 'Uygulama'],
            ['java', 'Java Uygulamasi', 'Uygulama'],
            ['discord', 'Discord Bot', 'Bot'],
        ];

        foreach ($map as [$needle, $label, $family]) {
            if (str_contains($haystack, $needle)) {
                return [$label, $family];
            }
        }

        if ($egg !== 'Unknown') {
            return [$egg, $nest !== 'Unknown' ? $nest : 'Diger'];
        }

        if ($nest !== 'Unknown') {
            return [$nest, 'Diger'];
        }

        return ['Bilinmiyor', 'Diger'];
    }

    private function mapResourceFailureToState(string $message): string
    {
        if (str_contains($message, 'HTTP 409')) {
            return 'transition';
        }

        if (str_contains($message, 'HTTP 404')) {
            return 'missing';
        }

        if (str_contains($message, 'HTTP 403')) {
            return 'restricted';
        }

        return 'untracked';
    }

    private function fakeOverview(): array
    {
        $now = time();
        $base = [
            ['identifier' => 'alpha1', 'name' => 'Survival EU-1', 'node' => 'Node-A', 'owner' => 'ahmet', 'type' => ['label' => 'Minecraft Paper', 'family' => 'Oyun Sunucusu', 'runtime' => 'java', 'nest' => 'Minecraft', 'egg' => 'Paper']],
            ['identifier' => 'beta2', 'name' => 'Skyblock TR', 'node' => 'Node-A', 'owner' => 'mert', 'type' => ['label' => 'Minecraft Forge', 'family' => 'Oyun Sunucusu', 'runtime' => 'java', 'nest' => 'Minecraft', 'egg' => 'Forge']],
            ['identifier' => 'gamma3', 'name' => 'Proxy Core', 'node' => 'Node-B', 'owner' => 'ops', 'type' => ['label' => 'Minecraft Velocity', 'family' => 'Proxy', 'runtime' => 'java', 'nest' => 'Minecraft', 'egg' => 'Velocity']],
            ['identifier' => 'delta4', 'name' => 'Node Bot', 'node' => 'Node-C', 'owner' => 'ayse', 'type' => ['label' => 'Node.js Bot', 'family' => 'Bot', 'runtime' => 'node', 'nest' => 'Bots', 'egg' => 'Node.js']],
            ['identifier' => 'omega5', 'name' => 'Modpack Heavy', 'node' => 'Node-C', 'owner' => 'berk', 'type' => ['label' => 'Minecraft Modpack', 'family' => 'Oyun Sunucusu', 'runtime' => 'java', 'nest' => 'Minecraft', 'egg' => 'Modpack']],
        ];

        $servers = [];
        foreach ($base as $index => $server) {
            $wave = sin(($now / 18) + $index) * 8;
            $cpu = max(8, min(98, 30 + ($index * 12) + $wave));
            $memoryMb = max(512, min(24576, 1024 + ($index * 2200) + ($wave * 100)));
            $diskMb = max(10240, min(102400, 15000 + ($index * 8000) + ($wave * 250)));

            $servers[] = [
                'uuid' => 'demo-' . $server['identifier'],
                'identifier' => $server['identifier'],
                'name' => $server['name'],
                'description' => 'Demo server',
                'node' => $server['node'],
                'owner' => $server['owner'],
                'type' => $server['type'],
                'status' => $cpu > 90 ? 'starting' : 'running',
                'limits' => ['cpu' => 100, 'memory' => 32768, 'disk' => 204800],
                'current' => [
                    'cpu_absolute' => round($cpu, 1),
                    'memory_bytes' => $memoryMb * 1024 * 1024,
                    'disk_bytes' => $diskMb * 1024 * 1024,
                    'network_rx_bytes' => (40 + ($index * 20)) * 1024 * 1024,
                    'network_tx_bytes' => (25 + ($index * 18)) * 1024 * 1024,
                    'uptime' => 3600 * (4 + $index),
                ],
            ];
        }

        return [
            'servers' => $servers,
            'node_inventory' => [
                'Node-A' => ['name' => 'Node-A', 'fqdn' => 'node-a.local', 'memory_mb' => 65536, 'disk_mb' => 512000, 'cpu_limit' => 1600, 'server_count' => 0],
                'Node-B' => ['name' => 'Node-B', 'fqdn' => 'node-b.local', 'memory_mb' => 32768, 'disk_mb' => 256000, 'cpu_limit' => 800, 'server_count' => 0],
                'Node-C' => ['name' => 'Node-C', 'fqdn' => 'node-c.local', 'memory_mb' => 131072, 'disk_mb' => 1024000, 'cpu_limit' => 2400, 'server_count' => 0],
            ],
        ];
    }
}
