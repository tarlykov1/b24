<?php

declare(strict_types=1);

namespace MigrationModule\HealthMonitor;

use PDO;

final class FilesystemMonitor
{
    /** @return array<int,array<string,mixed>> */
    public function detect(PDO $pdo, string $jobId): array
    {
        $fileQueueStmt = $pdo->prepare('SELECT COUNT(*) FROM queue WHERE job_id=:job_id AND entity_type="files" AND status IN ("pending","retry")');
        $fileQueueStmt->execute(['job_id' => $jobId]);
        $fileBacklog = (int) ($fileQueueStmt->fetchColumn() ?: 0);

        $slowStmt = $pdo->prepare('SELECT COUNT(*) FROM logs WHERE job_id=:job_id AND created_at >= :window AND (message LIKE :p1 OR message LIKE :p2)');
        $slowStmt->execute([
            'job_id' => $jobId,
            'window' => (new \DateTimeImmutable('-5 minutes'))->format('Y-m-d H:i:s'),
            'p1' => '%io wait%',
            'p2' => '%upload slow%',
        ]);
        $slowSignals = (int) ($slowStmt->fetchColumn() ?: 0);

        if ($fileBacklog < 100 && $slowSignals === 0) {
            return [];
        }

        return [[
            'code' => 'filesystem_bottleneck',
            'severity' => 'warning',
            'message' => 'Filesystem bottleneck detected',
            'file_backlog' => $fileBacklog,
            'io_wait_signals' => $slowSignals,
        ]];
    }
}
