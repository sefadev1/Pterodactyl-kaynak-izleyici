<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

final class HttpClient
{
    public function getJson(string $url, array $headers = [], int $timeoutSeconds = 10, bool $verifySsl = true): array
    {
        if (function_exists('curl_init')) {
            return $this->getJsonWithCurl($url, $headers, $timeoutSeconds, $verifySsl);
        }

        try {
            return $this->getJsonWithStreams($url, $headers, $timeoutSeconds, $verifySsl);
        } catch (\Throwable $streamError) {
            return $this->getJsonWithCurlBinary($url, $headers, $timeoutSeconds, $verifySsl, $streamError);
        }
    }

    private function getJsonWithCurl(string $url, array $headers, int $timeoutSeconds, bool $verifySsl): array
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => $timeoutSeconds,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        ]);

        $body = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('HTTP request failed: ' . $error);
        }

        if ($statusCode >= 400) {
            throw new RuntimeException(sprintf('HTTP %d returned from %s', $statusCode, $url));
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid JSON response from API.');
        }

        return $decoded;
    }

    private function getJsonWithStreams(string $url, array $headers, int $timeoutSeconds, bool $verifySsl): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => $timeoutSeconds,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => $verifySsl,
                'verify_peer_name' => $verifySsl,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        $responseHeaders = $http_response_header ?? [];
        $statusLine = $responseHeaders[0] ?? '';
        preg_match('/\s(\d{3})\s/', $statusLine, $matches);
        $statusCode = isset($matches[1]) ? (int) $matches[1] : 0;

        if ($body === false) {
            throw new RuntimeException(sprintf('HTTP request failed for %s', $url));
        }

        if ($statusCode >= 400) {
            throw new RuntimeException(sprintf('HTTP %d returned from %s', $statusCode, $url));
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid JSON response from API.');
        }

        return $decoded;
    }

    private function getJsonWithCurlBinary(
        string $url,
        array $headers,
        int $timeoutSeconds,
        bool $verifySsl,
        \Throwable $previous
    ): array {
        if (!function_exists('shell_exec')) {
            throw new RuntimeException('HTTP request failed and shell_exec is unavailable.', 0, $previous);
        }

        $curlPath = trim((string) shell_exec('where curl'));
        if ($curlPath === '') {
            throw new RuntimeException('HTTP request failed and curl.exe is not available.', 0, $previous);
        }

        $segments = [];
        $segments[] = escapeshellarg(strtok($curlPath, PHP_EOL));
        $segments[] = '--silent';
        $segments[] = '--show-error';
        $segments[] = '--location';
        $segments[] = '--max-time ' . (int) $timeoutSeconds;
        $segments[] = '--write-out "\n%{http_code}"';

        if (!$verifySsl) {
            $segments[] = '--insecure';
        }

        foreach ($headers as $header) {
            $segments[] = '-H ' . escapeshellarg($header);
        }

        $segments[] = escapeshellarg($url);
        $output = shell_exec(implode(' ', $segments));

        if (!is_string($output) || $output === '') {
            throw new RuntimeException('curl.exe request failed.', 0, $previous);
        }

        $parts = preg_split("/\r?\n/", trim($output));
        $statusCode = (int) array_pop($parts);
        $body = implode("\n", $parts);

        if ($statusCode >= 400) {
            throw new RuntimeException(sprintf('HTTP %d returned from %s', $statusCode, $url), 0, $previous);
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid JSON response from API.', 0, $previous);
        }

        return $decoded;
    }
}
