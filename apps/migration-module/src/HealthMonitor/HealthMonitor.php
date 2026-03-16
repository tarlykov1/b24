<?php

declare(strict_types=1);

namespace MigrationModule\HealthMonitor;

use PDO;

final class HealthMonitor
{
    public const SAMPLE_INTERVAL_SECONDS = 10;

    public function __construct(
        private readonly RetryAnalyzer $retryAnalyzer,
        private readonly QueueMonitor $queueMonitor,
        private readonly WorkerWatchdog $workerWatchdog,
        private readonly RestThrottleDetector $restThrottleDetector,
        private readonly FilesystemMonitor $filesystemMonitor,
        private readonly AutoRecoveryEngine $recoveryEngine,
    ) {
    }

    /** @return array<string,mixed> */
    public function check(PDO $pdo, string $jobId, bool $applyRecovery = false): array
    {
        $issues = array_merge(
            $this->retryAnalyzer->detect($pdo, $jobId),
            $this->queueMonitor->detect($pdo, $jobId),
            $this->workerWatchdog->detect($pdo, $jobId),
            $this->restThrottleDetector->detect($pdo, $jobId),
            $this->filesystemMonitor->detect($pdo, $jobId),
        );

        $score = $this->score($issues);
        $metrics = $this->collectMetrics($pdo, $jobId);
        $actions = [];

        if ($applyRecovery) {
            $actions = $this->recoveryEngine->recover($pdo, $jobId, $issues);
        }

        return [
            'job_id' => $jobId,
            'sample_interval_seconds' => self::SAMPLE_INTERVAL_SECONDS,
            'metrics' => $metrics,
            'health_score' => $score,
            'issues' => $issues,
            'recovery_actions' => $actions,
        ];
    }

    /** @param array<int,array<string,mixed>> $issues */
    private function score(array $issues): int
    {
        $score = 100;
        foreach ($issues as $issue) {
            $severity = (string) ($issue['severity'] ?? 'warning');
            $score -= $severity === 'critical' ? 20 : 8;
        }

        return max(0, $score);
    }

    /** @return array<string,mixed> */
    private function collectMetrics(PDO $pdo, string $jobId): array
    {
        $pending = (int) ($pdo->query("SELECT COUNT(*) FROM queue WHERE job_id=" . $pdo->quote($jobId) . " AND status IN ('pending','retry')")->fetchColumn() ?: 0);
        $failed = (int) ($pdo->query("SELECT COUNT(*) FROM queue WHERE job_id=" . $pdo->quote($jobId) . " AND status='failed'")->fetchColumn() ?: 0);

        $stateStmt = $pdo->prepare('SELECT status FROM state WHERE entity_type=:entity_type LIMIT 1');
        $stateStmt->execute(['entity_type' => 'health_runtime']);
        $runtime = json_decode((string) ($stateStmt->fetchColumn() ?: '{}'), true);
        $runtime = is_array($runtime) ? $runtime : [];

        $workers = (int) ($runtime['worker_concurrency'] ?? 0);
        $retryBackoff = (int) ($runtime['retry_backoff_seconds'] ?? 3);
        $restDelay = (int) ($runtime['rest_delay_ms'] ?? 0);

        return [
            'workers' => $workers,
            'queue_depth' => $pending,
            'failed' => $failed,
            'retry_backoff_seconds' => $retryBackoff,
            'rest_delay_ms' => $restDelay,
            'api_latency_ms' => (int) (($runtime['api_latency_ms'] ?? 0)),
        ];
    }
}
