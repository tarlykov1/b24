<?php

declare(strict_types=1);

namespace MigrationModule\HealthMonitor;

use PDO;

final class QueueMonitor
{
    /** @return array<int,array<string,mixed>> */
    public function detect(PDO $pdo, string $jobId): array
    {
        $pendingStmt = $pdo->prepare('SELECT COUNT(*) FROM queue WHERE job_id=:job_id AND status IN ("pending","retry")');
        $pendingStmt->execute(['job_id' => $jobId]);
        $pending = (int) ($pendingStmt->fetchColumn() ?: 0);

        $updatedStmt = $pdo->prepare('SELECT MAX(updated_at) FROM queue WHERE job_id=:job_id AND status IN ("pending","retry")');
        $updatedStmt->execute(['job_id' => $jobId]);
        $lastUpdate = (string) ($updatedStmt->fetchColumn() ?: '');

        if ($pending === 0 || $lastUpdate === '') {
            return [];
        }

        $stalledSince = strtotime($lastUpdate);
        if ($stalledSince === false || (time() - $stalledSince) < 600) {
            return [];
        }

        $workersRunning = $this->workersRunning($pdo, $jobId);

        return [[
            'code' => 'queue_stall',
            'severity' => 'critical',
            'message' => 'Queue stalled',
            'pending_entities' => $pending,
            'workers_running' => $workersRunning,
        ]];
    }

    private function workersRunning(PDO $pdo, string $jobId): int
    {
        $stmt = $pdo->prepare('SELECT state_json FROM distributed_control_plane WHERE job_id=:job_id LIMIT 1');
        $stmt->execute(['job_id' => $jobId]);
        $state = json_decode((string) ($stmt->fetchColumn() ?: '{}'), true);
        if (!is_array($state)) {
            return 0;
        }

        $workers = $state['workers'] ?? [];
        if (!is_array($workers)) {
            return 0;
        }

        $running = 0;
        foreach ($workers as $worker) {
            if (is_array($worker) && (($worker['status'] ?? '') === 'running')) {
                $running++;
            }
        }

        return $running;
    }
}
