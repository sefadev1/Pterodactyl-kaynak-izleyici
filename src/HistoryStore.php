<?php

declare(strict_types=1);

namespace App;

final class HistoryStore
{
    public function load(): array
    {
        $file = (string) app_config('storage.history_file');
        if (!is_file($file)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($file), true);
        return is_array($decoded) ? $decoded : [];
    }

    public function append(array $overview): array
    {
        $history = $this->load();
        $snapshot = [
            'time' => date('H:i:s'),
            'captured_at' => date(DATE_ATOM),
            'servers' => [],
        ];

        foreach (($overview['servers'] ?? []) as $server) {
            $limits = $server['limits'];
            $current = $server['current'];
            $memoryPercent = $limits['memory'] > 0 ? ($current['memory_bytes'] / ($limits['memory'] * 1024 * 1024)) * 100 : 0;
            $diskPercent = $limits['disk'] > 0 ? ($current['disk_bytes'] / ($limits['disk'] * 1024 * 1024)) * 100 : 0;

            $snapshot['servers'][(string) $server['identifier']] = [
                'cpu_percent' => round((float) $current['cpu_absolute'], 2),
                'memory_percent' => round((float) $memoryPercent, 2),
                'disk_percent' => round((float) $diskPercent, 2),
            ];
        }

        $history[] = $snapshot;
        $history = array_slice($history, -1 * (int) app_config('app.history_limit', 120));

        $file = (string) app_config('storage.history_file');
        $directory = dirname($file);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($file, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $history;
    }
}
