<?php

declare(strict_types=1);

namespace MigrationModule\HealthMonitor;

use PDO;

final class RetryAnalyzer
{
    /** @return array<int,array<string,mixed>> */
    public function detect(PDO $pdo, string $jobId): array
    {
        $window = (new \DateTimeImmutable('-5 minutes'))->format('Y-m-d H:i:s');
        $totalStmt = $pdo->prepare('SELECT COUNT(*) FROM queue WHERE job_id=:job_id AND updated_at >= :window');
        $totalStmt->execute(['job_id' => $jobId, 'window' => $window]);
        $total = (int) ($totalStmt->fetchColumn() ?: 0);

        $retryStmt = $pdo->prepare('SELECT COUNT(*) FROM queue WHERE job_id=:job_id AND status="retry" AND updated_at >= :window');
        $retryStmt->execute(['job_id' => $jobId, 'window' => $window]);
        $retries = (int) ($retryStmt->fetchColumn() ?: 0);

        $retryRate = $total > 0 ? ($retries / $total) * 100 : 0.0;
        if ($retryRate <= 3.0) {
            return [];
        }

        $entityStmt = $pdo->prepare('SELECT entity_type, COUNT(*) c FROM queue WHERE job_id=:job_id AND status="retry" GROUP BY entity_type ORDER BY c DESC LIMIT 1');
        $entityStmt->execute(['job_id' => $jobId]);
        $entity = (string) (($entityStmt->fetch(PDO::FETCH_ASSOC)['entity_type'] ?? 'unknown'));

        return [[
            'code' => 'retry_storm',
            'severity' => 'warning',
            'message' => 'WARNING: retry storm detected',
            'entity' => $entity,
            'retry_rate' => round($retryRate, 2),
            'baseline' => 0.3,
        ]];
    }
}
