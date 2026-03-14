<?php

declare(strict_types=1);

namespace MigrationModule\Prototype\Storage;

use PDO;

final class SqliteStorage
{
    private PDO $pdo;

    public function __construct(string $path)
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $this->pdo = new PDO('sqlite:' . $path);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function initSchema(): void
    {
        $sql = file_get_contents(__DIR__ . '/../../../../../db/prototype_schema.sql');
        $this->pdo->exec((string) $sql);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function createJob(string $mode): string
    {
        $id = 'job_' . bin2hex(random_bytes(4));
        $stmt = $this->pdo->prepare('INSERT INTO jobs(id, mode, status) VALUES(:id,:mode,:status)');
        $stmt->execute(['id' => $id, 'mode' => $mode, 'status' => 'running']);
        return $id;
    }

    public function setJobStatus(string $jobId, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE jobs SET status=:status, updated_at=CURRENT_TIMESTAMP WHERE id=:id');
        $stmt->execute(['id' => $jobId, 'status' => $status]);
    }

    public function saveQueue(string $jobId, string $entityType, string $sourceId, string $payload, string $status = 'pending'): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO queue(job_id, entity_type, source_id, payload, status) VALUES(:job_id,:entity_type,:source_id,:payload,:status)');
        $stmt->execute(['job_id' => $jobId, 'entity_type' => $entityType, 'source_id' => $sourceId, 'payload' => $payload, 'status' => $status]);
    }

    /** @return array<int,array<string,mixed>> */
    public function pendingQueue(string $jobId, int $limit): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM queue WHERE job_id=:job_id AND status IN ("pending","retry") ORDER BY id LIMIT :limit');
        $stmt->bindValue(':job_id', $jobId);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function markQueueStatus(int $id, string $status, int $attempt): void
    {
        $stmt = $this->pdo->prepare('UPDATE queue SET status=:status, attempt=:attempt, updated_at=CURRENT_TIMESTAMP WHERE id=:id');
        $stmt->execute(['id' => $id, 'status' => $status, 'attempt' => $attempt]);
    }

    public function saveMapping(string $jobId, string $entityType, string $sourceId, string $targetId, string $checksum, string $status, int $conflict): void
    {
        $stmt = $this->pdo->prepare('INSERT OR REPLACE INTO entity_map(job_id, entity_type, source_id, target_id, checksum, status, conflict_marker, updated_at) VALUES(:job_id,:entity_type,:source_id,:target_id,:checksum,:status,:conflict,CURRENT_TIMESTAMP)');
        $stmt->execute(['job_id' => $jobId, 'entity_type' => $entityType, 'source_id' => $sourceId, 'target_id' => $targetId, 'checksum' => $checksum, 'status' => $status, 'conflict' => $conflict]);
    }

    public function findMapping(string $entityType, string $sourceId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM entity_map WHERE entity_type=:entity_type AND source_id=:source_id ORDER BY updated_at DESC LIMIT 1');
        $stmt->execute(['entity_type' => $entityType, 'source_id' => $sourceId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r === false ? null : $r;
    }

    public function saveCheckpoint(string $jobId, string $entityType, string $lastSourceId): void
    {
        $stmt = $this->pdo->prepare('INSERT OR REPLACE INTO checkpoint(job_id, entity_type, last_source_id, updated_at) VALUES(:job_id,:entity_type,:last_source_id,CURRENT_TIMESTAMP)');
        $stmt->execute(['job_id' => $jobId, 'entity_type' => $entityType, 'last_source_id' => $lastSourceId]);
    }

    public function saveLog(string $jobId, string $level, string $message): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO logs(job_id, level, message) VALUES(:job_id,:level,:message)');
        $stmt->execute(['job_id' => $jobId, 'level' => $level, 'message' => $message]);
    }


    public function saveStructuredLog(string $jobId, string $level, string $entityType, string $entityId, string $action, string $result, float $duration, ?string $error = null): void
    {
        $payload = [
            'timestamp' => date(DATE_ATOM),
            'job_id' => $jobId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'result' => $result,
            'duration' => round($duration, 4),
            'error' => $error,
        ];

        $this->saveLog($jobId, $level, json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    public function saveDiff(string $jobId, string $entityType, string $sourceId, string $category, string $detail): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO diff(job_id, entity_type, source_id, category, detail) VALUES(:job_id,:entity_type,:source_id,:category,:detail)');
        $stmt->execute(['job_id' => $jobId, 'entity_type' => $entityType, 'source_id' => $sourceId, 'category' => $category, 'detail' => $detail]);
    }

    public function saveIntegrity(string $jobId, string $entityType, string $sourceId, string $issue): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO integrity_issues(job_id, entity_type, source_id, issue) VALUES(:job_id,:entity_type,:source_id,:issue)');
        $stmt->execute(['job_id' => $jobId, 'entity_type' => $entityType, 'source_id' => $sourceId, 'issue' => $issue]);
    }

    /** @return array<string,mixed> */
    public function summary(string $jobId): array
    {
        $tables = ['queue', 'entity_map', 'diff', 'integrity_issues', 'logs'];
        $summary = [];
        foreach ($tables as $table) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as c FROM {$table} WHERE job_id=:job_id");
            $stmt->execute(['job_id' => $jobId]);
            $summary[$table] = (int) $stmt->fetchColumn();
        }
        $stmt = $this->pdo->prepare('SELECT status FROM jobs WHERE id=:job_id');
        $stmt->execute(['job_id' => $jobId]);
        $status = $stmt->fetchColumn();
        $summary['status'] = $status ?: 'unknown';
        return $summary;
    }
}
