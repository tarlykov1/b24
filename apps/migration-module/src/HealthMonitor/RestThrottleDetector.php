<?php

declare(strict_types=1);

namespace MigrationModule\HealthMonitor;

use PDO;

final class RestThrottleDetector
{
    /** @return array<int,array<string,mixed>> */
    public function detect(PDO $pdo, string $jobId): array
    {
        $window = (new \DateTimeImmutable('-5 minutes'))->format('Y-m-d H:i:s');
        $stmt = $pdo->prepare('SELECT message FROM logs WHERE job_id=:job_id AND created_at >= :window ORDER BY id DESC LIMIT 300');
        $stmt->execute(['job_id' => $jobId, 'window' => $window]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $throttleHits = 0;
        $latencies = [];
        foreach ($rows as $message) {
            $text = mb_strtolower((string) $message);
            if (str_contains($text, '429') || str_contains($text, 'throttle') || str_contains($text, 'rate-limit')) {
                $throttleHits++;
            }

            $decoded = json_decode((string) $message, true);
            if (is_array($decoded) && isset($decoded['duration'])) {
                $latencies[] = (float) $decoded['duration'];
            }
        }

        $avg = $latencies === [] ? 0.0 : array_sum($latencies) / count($latencies);
        if ($throttleHits === 0 && $avg < 2.0) {
            return [];
        }

        return [[
            'code' => 'rest_throttling',
            'severity' => 'warning',
            'message' => 'REST throttling detected',
            'avg_latency_seconds' => round($avg, 2),
            'throttle_hits' => $throttleHits,
        ]];
    }
}
