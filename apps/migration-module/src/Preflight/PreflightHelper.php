<?php

declare(strict_types=1);

namespace MigrationModule\Preflight;

final class PreflightHelper
{
    /** @return list<array<string,mixed>> */
    public static function recordsByAdapter(object $adapter, string $entityType, int $batch = 50, int $max = 500): array
    {
        if (!method_exists($adapter, 'fetch')) {
            return [];
        }

        $rows = [];
        $offset = 0;
        while (count($rows) < $max) {
            $chunk = $adapter->fetch($entityType, $offset, min($batch, $max - count($rows)));
            if (!is_array($chunk) || $chunk === []) {
                break;
            }
            foreach ($chunk as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }
            if (count($chunk) < $batch) {
                break;
            }
            $offset += count($chunk);
        }

        return $rows;
    }

    /** @return array{ok:bool,duration_ms:float,error?:string,raw?:array<string,mixed>} */
    public static function probeRestUserCurrent(string $webhookUrl): array
    {
        $url = rtrim($webhookUrl, '/');
        if (!str_ends_with($url, '.json')) {
            $url .= '/user.current.json';
        }
        $start = microtime(true);
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'duration_ms' => 0, 'error' => 'curl_init_failed'];
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $duration = (microtime(true) - $start) * 1000;
        if (!is_string($raw) || $raw === '') {
            return ['ok' => false, 'duration_ms' => $duration, 'error' => $errno !== 0 ? $error : 'empty_response'];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'duration_ms' => $duration, 'error' => 'invalid_json'];
        }

        if ($code >= 400 || isset($decoded['error'])) {
            return ['ok' => false, 'duration_ms' => $duration, 'error' => (string) ($decoded['error_description'] ?? $decoded['error'] ?? ('http_' . $code)), 'raw' => $decoded];
        }

        return ['ok' => true, 'duration_ms' => $duration, 'raw' => $decoded];
    }
}
