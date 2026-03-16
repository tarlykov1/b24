<?php

declare(strict_types=1);

namespace MigrationModule\HealthMonitor;

use PDO;

final class WorkerWatchdog
{
    /** @return array<int,array<string,mixed>> */
    public function detect(PDO $pdo, string $jobId): array
    {
        $stmt = $pdo->prepare('SELECT state_json FROM distributed_control_plane WHERE job_id=:job_id LIMIT 1');
        $stmt->execute(['job_id' => $jobId]);
        $state = json_decode((string) ($stmt->fetchColumn() ?: '{}'), true);
        if (!is_array($state)) {
            return [];
        }

        $issues = [];
        foreach (($state['workers'] ?? []) as $workerId => $worker) {
            if (!is_array($worker)) {
                continue;
            }

            $last = (string) ($worker['last_processed_at'] ?? $worker['last_heartbeat'] ?? '');
            if ($last === '') {
                continue;
            }

            $age = time() - ((int) strtotime($last));
            if ($age > 300) {
                $issues[] = [
                    'code' => 'worker_idle',
                    'severity' => 'critical',
                    'message' => sprintf('worker #%s stuck', (string) $workerId),
                    'last_processed_seconds_ago' => $age,
                ];
            }
        }

        return $issues;
    }
}
