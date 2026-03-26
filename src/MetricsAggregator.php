<?php

declare(strict_types=1);

namespace App;

final class MetricsAggregator
{
    public function build(array $overview, array $history): array
    {
        $servers = [];
        $totals = [
            'cpu_percent' => 0.0,
            'memory_bytes' => 0.0,
            'disk_bytes' => 0.0,
            'network_rx_bytes' => 0.0,
            'network_tx_bytes' => 0.0,
        ];
        $nodeInventory = $overview['node_inventory'] ?? [];
        $nodes = [];

        foreach (($overview['servers'] ?? []) as $server) {
            $limits = $server['limits'];
            $current = $server['current'];

            $cpuPercent = (float) $current['cpu_absolute'];
            $memoryPercent = $limits['memory'] > 0 ? ($current['memory_bytes'] / ($limits['memory'] * 1024 * 1024)) * 100 : 0;
            $diskPercent = $limits['disk'] > 0 ? ($current['disk_bytes'] / ($limits['disk'] * 1024 * 1024)) * 100 : 0;

            $trend = $this->buildTrend((string) $server['identifier'], $history);
            $severity = $this->resolveSeverity($cpuPercent, $memoryPercent, $diskPercent);
            $weightedScore = ($cpuPercent * 0.5) + ($memoryPercent * 0.3) + ($diskPercent * 0.2);

            $row = [
                'uuid' => $server['uuid'],
                'identifier' => $server['identifier'],
                'name' => $server['name'],
                'description' => $server['description'],
                'node' => $server['node'],
                'owner' => $server['owner'],
                'type' => $server['type'] ?? [
                    'label' => 'Bilinmiyor',
                    'family' => 'Diger',
                    'runtime' => 'Unknown',
                    'nest' => 'Unknown',
                    'egg' => 'Unknown',
                ],
                'status' => $server['status'],
                'severity' => $severity,
                'weighted_score' => round($weightedScore, 2),
                'metrics' => [
                    'cpu_percent' => round($cpuPercent, 2),
                    'memory_bytes' => (int) round((float) $current['memory_bytes']),
                    'memory_percent' => round($memoryPercent, 2),
                    'disk_bytes' => (int) round((float) $current['disk_bytes']),
                    'disk_percent' => round($diskPercent, 2),
                    'network_rx_bytes' => (int) round((float) $current['network_rx_bytes']),
                    'network_tx_bytes' => (int) round((float) $current['network_tx_bytes']),
                    'uptime' => (int) $current['uptime'],
                ],
                'limits' => [
                    'cpu' => (float) $limits['cpu'],
                    'memory_mb' => (float) $limits['memory'],
                    'disk_mb' => (float) $limits['disk'],
                ],
                'trend' => $trend,
            ];

            $servers[] = $row;

            $totals['cpu_percent'] += $cpuPercent;
            $totals['memory_bytes'] += $current['memory_bytes'];
            $totals['disk_bytes'] += $current['disk_bytes'];
            $totals['network_rx_bytes'] += $current['network_rx_bytes'];
            $totals['network_tx_bytes'] += $current['network_tx_bytes'];

            $nodeName = (string) $server['node'];
            if (!isset($nodes[$nodeName])) {
                $inventory = $nodeInventory[$nodeName] ?? [
                    'name' => $nodeName,
                    'fqdn' => '',
                    'memory_mb' => 0.0,
                    'disk_mb' => 0.0,
                    'cpu_limit' => 0.0,
                    'server_count' => 0,
                ];

                $nodes[$nodeName] = [
                    'name' => $nodeName,
                    'fqdn' => (string) ($inventory['fqdn'] ?? ''),
                    'server_count' => 0,
                    'cpu_percent' => 0.0,
                    'memory_bytes' => 0.0,
                    'disk_bytes' => 0.0,
                    'capacity' => [
                        'memory_mb' => (float) ($inventory['memory_mb'] ?? 0),
                        'disk_mb' => (float) ($inventory['disk_mb'] ?? 0),
                        'cpu_limit' => (float) ($inventory['cpu_limit'] ?? 0),
                    ],
                ];
            }

            $nodes[$nodeName]['server_count']++;
            $nodes[$nodeName]['cpu_percent'] += $cpuPercent;
            $nodes[$nodeName]['memory_bytes'] += $current['memory_bytes'];
            $nodes[$nodeName]['disk_bytes'] += $current['disk_bytes'];
        }

        usort($servers, static fn(array $a, array $b): int => $b['weighted_score'] <=> $a['weighted_score']);
        $nodeList = array_values($nodes);
        foreach ($nodeList as &$node) {
            $memoryCapacityBytes = ((float) $node['capacity']['memory_mb']) * 1024 * 1024;
            $diskCapacityBytes = ((float) $node['capacity']['disk_mb']) * 1024 * 1024;
            $cpuCapacity = (float) $node['capacity']['cpu_limit'];

            $node['usage'] = [
                'memory_percent' => $memoryCapacityBytes > 0 ? round(($node['memory_bytes'] / $memoryCapacityBytes) * 100, 2) : 0.0,
                'disk_percent' => $diskCapacityBytes > 0 ? round(($node['disk_bytes'] / $diskCapacityBytes) * 100, 2) : 0.0,
                'cpu_percent' => $cpuCapacity > 0 ? round(($node['cpu_percent'] / $cpuCapacity) * 100, 2) : round((float) $node['cpu_percent'], 2),
            ];
        }
        unset($node);

        usort($nodeList, static fn(array $a, array $b): int => $b['cpu_percent'] <=> $a['cpu_percent']);

        return [
            'generated_at' => date(DATE_ATOM),
            'summary' => [
                'server_count' => count($servers),
                'critical_count' => count(array_filter($servers, static fn(array $server): bool => $server['severity'] === 'critical')),
                'warning_count' => count(array_filter($servers, static fn(array $server): bool => $server['severity'] === 'warning')),
                'cpu_percent_total' => round($totals['cpu_percent'], 2),
                'memory_bytes_total' => (int) round($totals['memory_bytes']),
                'disk_bytes_total' => (int) round($totals['disk_bytes']),
                'network_total_bytes' => (int) round($totals['network_rx_bytes'] + $totals['network_tx_bytes']),
            ],
            'nodes' => $nodeList,
            'servers' => $servers,
            'history' => $history,
        ];
    }

    private function resolveSeverity(float $cpuPercent, float $memoryPercent, float $diskPercent): string
    {
        $cpuCritical = (float) app_config('thresholds.cpu_critical', 90);
        $memoryCritical = (float) app_config('thresholds.memory_critical', 90);
        $diskCritical = (float) app_config('thresholds.disk_critical', 90);
        $cpuWarning = (float) app_config('thresholds.cpu_warning', 70);
        $memoryWarning = (float) app_config('thresholds.memory_warning', 70);
        $diskWarning = (float) app_config('thresholds.disk_warning', 75);

        if ($cpuPercent >= $cpuCritical || $memoryPercent >= $memoryCritical || $diskPercent >= $diskCritical) {
            return 'critical';
        }

        if ($cpuPercent >= $cpuWarning || $memoryPercent >= $memoryWarning || $diskPercent >= $diskWarning) {
            return 'warning';
        }

        return 'healthy';
    }

    private function buildTrend(string $identifier, array $history): array
    {
        $points = [];
        foreach ($history as $snapshot) {
            $metrics = $snapshot['servers'][$identifier] ?? null;
            if ($metrics === null) {
                continue;
            }

            $points[] = [
                'time' => $snapshot['time'],
                'cpu_percent' => $metrics['cpu_percent'],
                'memory_percent' => $metrics['memory_percent'],
                'disk_percent' => $metrics['disk_percent'] ?? 0,
            ];
        }

        return $points;
    }
}
