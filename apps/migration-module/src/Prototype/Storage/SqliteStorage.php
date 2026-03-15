<?php

declare(strict_types=1);

namespace MigrationModule\Prototype\Storage;

use PDO;
use DomainException;

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
        $stmt->execute(['id' => $id, 'mode' => $mode, 'status' => 'created']);
        return $id;
    }


    public function jobExists(string $jobId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM jobs WHERE id=:id LIMIT 1');
        $stmt->execute(['id' => $jobId]);

        return $stmt->fetchColumn() !== false;
    }

    public function assertJobExists(string $jobId): void
    {
        if ($this->jobExists($jobId)) {
            return;
        }

        throw new DomainException((string) json_encode([
            'error' => 'job_not_found',
            'job_id' => $jobId,
            'message' => 'Requested job-id does not exist.',
        ], JSON_UNESCAPED_UNICODE));
    }

    public function setJobStatus(string $jobId, string $status): void
    {
        $this->assertJobExists($jobId);
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



    /** @param array<string,mixed> $state */
    public function saveDistributedControlPlaneState(string $jobId, array $state): void
    {
        $stmt = $this->pdo->prepare('INSERT OR REPLACE INTO distributed_control_plane(job_id, state_json, updated_at) VALUES(:job_id,:state_json,CURRENT_TIMESTAMP)');
        $stmt->execute(['job_id' => $jobId, 'state_json' => (string) json_encode($state, JSON_UNESCAPED_UNICODE)]);
    }

    /** @return array<string,mixed>|null */
    public function distributedControlPlaneState(string $jobId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT state_json FROM distributed_control_plane WHERE job_id=:job_id LIMIT 1');
        $stmt->execute(['job_id' => $jobId]);
        $raw = $stmt->fetchColumn();
        if ($raw === false || !is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }



    /** @return array<string,mixed>|null */
    public function deltaCursor(string $jobId, string $entityType): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM delta_cursors WHERE job_id=:job_id AND entity_type=:entity_type LIMIT 1');
        $stmt->execute(['job_id' => $jobId, 'entity_type' => $entityType]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function saveDeltaCursor(string $jobId, string $entityType, ?string $lastSyncTimestamp, ?string $lastEntityId, ?string $watermark, string $phase): void
    {
        $stmt = $this->pdo->prepare('INSERT OR REPLACE INTO delta_cursors(job_id, entity_type, last_sync_timestamp, last_entity_id, watermark, phase, updated_at) VALUES(:job_id,:entity_type,:last_sync_timestamp,:last_entity_id,:watermark,:phase,CURRENT_TIMESTAMP)');
        $stmt->execute([
            'job_id' => $jobId,
            'entity_type' => $entityType,
            'last_sync_timestamp' => $lastSyncTimestamp,
            'last_entity_id' => $lastEntityId,
            'watermark' => $watermark,
            'phase' => $phase,
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    public function deltaStates(string $jobId, string $entityType): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM delta_entity_state WHERE job_id=:job_id AND entity_type=:entity_type');
        $stmt->execute(['job_id' => $jobId, 'entity_type' => $entityType]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function upsertDeltaState(string $jobId, string $entityType, string $entityId, string $fingerprint, ?string $ownerKey, ?string $updatedAt, int $deleted): void
    {
        $stmt = $this->pdo->prepare('INSERT OR REPLACE INTO delta_entity_state(job_id, entity_type, entity_id, fingerprint, owner_key, updated_at, deleted, last_seen_at) VALUES(:job_id,:entity_type,:entity_id,:fingerprint,:owner_key,:updated_at,:deleted,CURRENT_TIMESTAMP)');
        $stmt->execute([
            'job_id' => $jobId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'fingerprint' => $fingerprint,
            'owner_key' => $ownerKey,
            'updated_at' => $updatedAt,
            'deleted' => $deleted,
        ]);
    }

    public function saveDeltaChange(string $jobId, string $scanId, string $phase, string $entityType, string $entityId, string $action, string $fingerprint, array $payload): void
    {
        $stmt = $this->pdo->prepare('INSERT OR IGNORE INTO delta_changes(job_id, scan_id, phase, entity_type, entity_id, action, fingerprint, payload, status) VALUES(:job_id,:scan_id,:phase,:entity_type,:entity_id,:action,:fingerprint,:payload,:status)');
        $stmt->execute([
            'job_id' => $jobId,
            'scan_id' => $scanId,
            'phase' => $phase,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'fingerprint' => $fingerprint,
            'payload' => (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'pending',
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    public function pendingDeltaChanges(string $jobId, ?string $scanId = null): array
    {
        $sql = 'SELECT * FROM delta_changes WHERE job_id=:job_id AND status="pending"';
        $params = ['job_id' => $jobId];
        if ($scanId !== null && $scanId !== '') {
            $sql .= ' AND scan_id=:scan_id';
            $params['scan_id'] = $scanId;
        }
        $sql .= ' ORDER BY id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function markDeltaChangeApplied(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE delta_changes SET status=:status, applied_at=CURRENT_TIMESTAMP WHERE id=:id');
        $stmt->execute(['status' => 'applied', 'id' => $id]);
    }

    /** @return array<string,mixed> */
    public function deltaStatus(string $jobId): array
    {
        $totals = [
            'pending' => 0,
            'applied' => 0,
            'total' => 0,
        ];

        $stmt = $this->pdo->prepare('SELECT status, COUNT(*) as c FROM delta_changes WHERE job_id=:job_id GROUP BY status');
        $stmt->execute(['job_id' => $jobId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $status = (string) ($row['status'] ?? 'pending');
            $count = (int) ($row['c'] ?? 0);
            $totals[$status] = $count;
            $totals['total'] += $count;
        }

        $byAction = [];
        $stmt = $this->pdo->prepare('SELECT action, COUNT(*) as c FROM delta_changes WHERE job_id=:job_id GROUP BY action');
        $stmt->execute(['job_id' => $jobId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $byAction[(string) $row['action']] = (int) $row['c'];
        }

        $cursors = [];
        $stmt = $this->pdo->prepare('SELECT entity_type, last_sync_timestamp, last_entity_id, watermark, phase, updated_at FROM delta_cursors WHERE job_id=:job_id ORDER BY entity_type');
        $stmt->execute(['job_id' => $jobId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $cursors[] = $row;
        }

        return ['totals' => $totals, 'actions' => $byAction, 'cursors' => $cursors];
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
