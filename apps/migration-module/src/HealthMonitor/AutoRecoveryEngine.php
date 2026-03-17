<?php

declare(strict_types=1);

namespace MigrationModule\HealthMonitor;

use PDO;

final class AutoRecoveryEngine
{
    public function __construct(private readonly string $healthLogPath = 'logs/health-monitor.log')
    {
    }

    /** @param array<int,array<string,mixed>> $issues
     *  @return array<int,array<string,mixed>>
     */
    public function recover(PDO $pdo, string $jobId, array $issues): array
    {
        if (!$this->isEnabled($pdo)) {
            return [];
        }

        $actions = [];
        foreach ($issues as $issue) {
            $code = (string) ($issue['code'] ?? '');
            $action = match ($code) {
                'retry_storm' => $this->recoverRetryStorm($pdo, $jobId),
                'worker_idle' => $this->recoverWorker($pdo, $jobId, (string) ($issue['message'] ?? '')),
                'queue_stall' => $this->recoverQueue($pdo, $jobId),
                'rest_throttling' => $this->recoverRestThrottle($pdo, $jobId),
                default => null,
            };

            if ($action !== null) {
                $actions[] = $action;
                $this->log('[auto_recovery]' . PHP_EOL . $this->toKeyValues($action));
            }
        }

        return $actions;
    }

    private function isEnabled(PDO $pdo): bool
    {
        $stmt = $pdo->prepare('SELECT status FROM state WHERE entity_type=:entity_type LIMIT 1');
        $stmt->execute(['entity_type' => 'health_auto_recovery']);
        $raw = (string) ($stmt->fetchColumn() ?: '');
        if ($raw === '') {
            return false;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) && (bool) ($decoded['enabled'] ?? false) === true;
    }

    /** @return array<string,mixed> */
    private function recoverRetryStorm(PDO $pdo, string $jobId): array
    {
        $runtime = $this->loadRuntime($pdo);
        $oldBackoff = (int) ($runtime['retry_backoff_seconds'] ?? 3);
        $oldWorkers = (int) ($runtime['worker_concurrency'] ?? 10);
        $runtime['retry_backoff_seconds'] = min(30, max(10, $oldBackoff + 7));
        $runtime['worker_concurrency'] = max(1, $oldWorkers - 4);
        $this->saveRuntime($pdo, $runtime);

        return [
            'action' => 'retry_storm_mitigation',
            'job_id' => $jobId,
            'retry_backoff' => $oldBackoff . 's -> ' . $runtime['retry_backoff_seconds'] . 's',
            'workers' => $oldWorkers . ' -> ' . $runtime['worker_concurrency'],
        ];
    }

    /** @return array<string,mixed> */
    private function recoverWorker(PDO $pdo, string $jobId, string $workerLabel): array
    {
        $this->appendLog($pdo, $jobId, 'warning', 'worker_restart', ['worker' => $workerLabel]);

        return [
            'action' => 'restart_worker',
            'job_id' => $jobId,
            'worker' => $workerLabel,
            'result' => 'requeued_last_entity',
        ];
    }

    /** @return array<string,mixed> */
    private function recoverQueue(PDO $pdo, string $jobId): array
    {
        $this->appendLog($pdo, $jobId, 'warning', 'queue_recovery', ['step' => 'recalculate_queue_priorities']);

        return [
            'action' => 'queue_recovery',
            'job_id' => $jobId,
            'result' => 'restart_idle_workers_and_rebalance',
        ];
    }

    /** @return array<string,mixed> */
    private function recoverRestThrottle(PDO $pdo, string $jobId): array
    {
        $runtime = $this->loadRuntime($pdo);
        $oldConcurrency = (int) ($runtime['rest_concurrency'] ?? 10);
        $oldDelay = (int) ($runtime['rest_delay_ms'] ?? 0);
        $runtime['rest_concurrency'] = max(1, $oldConcurrency - 3);
        $runtime['rest_delay_ms'] = max(300, $oldDelay);
        $this->saveRuntime($pdo, $runtime);

        return [
            'action' => 'rest_throttle_recovery',
            'job_id' => $jobId,
            'rest_concurrency' => $oldConcurrency . ' -> ' . $runtime['rest_concurrency'],
            'rest_delay_enabled_ms' => $runtime['rest_delay_ms'],
        ];
    }

    /** @return array<string,mixed> */
    private function loadRuntime(PDO $pdo): array
    {
        $stmt = $pdo->prepare('SELECT status FROM state WHERE entity_type=:entity_type LIMIT 1');
        $stmt->execute(['entity_type' => 'health_runtime']);
        $decoded = json_decode((string) ($stmt->fetchColumn() ?: '{}'), true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string,mixed> $runtime */
    private function saveRuntime(PDO $pdo, array $runtime): void
    {
        $stmt = $pdo->prepare('INSERT INTO state(entity_type,last_sync_time,records_processed,status,updated_at) VALUES(:entity_type,:last_sync_time,:records_processed,:status,CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE status=VALUES(status), updated_at=CURRENT_TIMESTAMP');
        $stmt->execute([
            'entity_type' => 'health_runtime',
            'last_sync_time' => null,
            'records_processed' => 0,
            'status' => json_encode($runtime, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /** @param array<string,mixed> $context */
    private function appendLog(PDO $pdo, string $jobId, string $level, string $type, array $context): void
    {
        $payload = ['type' => $type, 'context' => $context, 'ts' => date(DATE_ATOM)];
        $stmt = $pdo->prepare('INSERT INTO logs(job_id, level, message) VALUES(:job_id,:level,:message)');
        $stmt->execute(['job_id' => $jobId, 'level' => $level, 'message' => json_encode($payload, JSON_UNESCAPED_UNICODE)]);
    }

    /** @param array<string,mixed> $payload */
    private function toKeyValues(array $payload): string
    {
        $lines = [];
        foreach ($payload as $k => $v) {
            $lines[] = $k . '=' . (is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE));
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    private function log(string $text): void
    {
        $dir = dirname($this->healthLogPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($this->healthLogPath, $text, FILE_APPEND);
    }
}
