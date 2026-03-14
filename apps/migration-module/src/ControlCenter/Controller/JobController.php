<?php

declare(strict_types=1);

namespace MigrationModule\ControlCenter\Controller;

use PDO;

final class JobController
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string,mixed> */
    public function pause(string $jobId): array
    {
        return $this->updateStatus($jobId, 'paused', '[operator_action] paused migration');
    }

    /** @return array<string,mixed> */
    public function resume(string $jobId): array
    {
        return $this->updateStatus($jobId, 'execution', '[operator_action] resumed migration');
    }

    /** @return array<string,mixed> */
    public function retryEntity(string $jobId, string $entityType, string $sourceId): array
    {
        $stmt = $this->pdo->prepare('UPDATE queue SET status = "retry", updated_at = CURRENT_TIMESTAMP WHERE job_id = :job_id AND entity_type = :entity_type AND source_id = :source_id');
        $stmt->execute(['job_id' => $jobId, 'entity_type' => $entityType, 'source_id' => $sourceId]);
        $this->log($jobId, 'manual_override', sprintf('retry %s:%s', $entityType, $sourceId));

        return ['job_id' => $jobId, 'status' => 'retry_queued', 'entity_type' => $entityType, 'source_id' => $sourceId];
    }

    /** @return array<string,mixed> */
    public function skipEntity(string $jobId, string $entityType, string $sourceId): array
    {
        $stmt = $this->pdo->prepare('UPDATE queue SET status = "skipped", updated_at = CURRENT_TIMESTAMP WHERE job_id = :job_id AND entity_type = :entity_type AND source_id = :source_id');
        $stmt->execute(['job_id' => $jobId, 'entity_type' => $entityType, 'source_id' => $sourceId]);
        $this->log($jobId, 'manual_override', sprintf('skip %s:%s', $entityType, $sourceId));

        return ['job_id' => $jobId, 'status' => 'skipped', 'entity_type' => $entityType, 'source_id' => $sourceId];
    }

    /** @return array<string,mixed> */
    public function forceResync(string $jobId, string $entityType, string $sourceId): array
    {
        $stmt = $this->pdo->prepare('UPDATE queue SET status = "pending", attempt = 0, updated_at = CURRENT_TIMESTAMP WHERE job_id = :job_id AND entity_type = :entity_type AND source_id = :source_id');
        $stmt->execute(['job_id' => $jobId, 'entity_type' => $entityType, 'source_id' => $sourceId]);
        $this->log($jobId, 'manual_override', sprintf('force re-sync %s:%s', $entityType, $sourceId));

        return ['job_id' => $jobId, 'status' => 'resync_forced', 'entity_type' => $entityType, 'source_id' => $sourceId];
    }

    /** @return array<string,mixed> */
    private function updateStatus(string $jobId, string $status, string $message): array
    {
        $stmt = $this->pdo->prepare('UPDATE jobs SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :job_id');
        $stmt->execute(['status' => $status, 'job_id' => $jobId]);
        $this->log($jobId, 'operator_action', $message);

        return ['job_id' => $jobId, 'status' => $status];
    }

    private function log(string $jobId, string $level, string $message): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO logs(job_id, level, message) VALUES(:job_id, :level, :message)');
        $stmt->execute(['job_id' => $jobId, 'level' => $level, 'message' => $message]);
    }
}
