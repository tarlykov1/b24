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


    public function jobStatus(string $jobId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT status FROM jobs WHERE id=:job_id LIMIT 1');
        $stmt->execute(['job_id' => $jobId]);
        $status = $stmt->fetchColumn();

        return is_string($status) ? $status : null;
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



    public function saveReconciliationResult(string $jobId, string $entity, string $entityId, string $status, string $diffType, ?string $diffDetails, string $severity): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO reconciliation_results(job_id, entity, entity_id, status, diff_type, diff_details, severity) VALUES(:job_id,:entity,:entity_id,:status,:diff_type,:diff_details,:severity)');
        $stmt->execute([
            'job_id' => $jobId,
            'entity' => $entity,
            'entity_id' => $entityId,
            'status' => $status,
            'diff_type' => $diffType,
            'diff_details' => $diffDetails,
            'severity' => $severity,
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    public function reconciliationResults(string $jobId, ?string $entity = null): array
    {
        $sql = 'SELECT * FROM reconciliation_results WHERE job_id=:job_id';
        $params = ['job_id' => $jobId];
        if ($entity !== null && $entity !== '') {
            $sql .= ' AND entity=:entity';
            $params['entity'] = $entity;
        }
        $sql .= ' ORDER BY id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function enqueueDelta(string $jobId, string $entityType, string $entityId, string $changeType, array $payload): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO delta_queue(job_id, entity_type, entity_id, change_type, payload, status) VALUES(:job_id,:entity_type,:entity_id,:change_type,:payload,:status)');
        $stmt->execute([
            'job_id' => $jobId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'change_type' => $changeType,
            'payload' => (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'pending',
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    public function pendingDeltaQueue(string $jobId, ?string $entityType = null, int $limit = 100): array
    {
        $sql = 'SELECT * FROM delta_queue WHERE job_id=:job_id AND status="pending"';
        $params = ['job_id' => $jobId];
        if ($entityType !== null && $entityType !== '') {
            $sql .= ' AND entity_type=:entity_type';
            $params['entity_type'] = $entityType;
        }
        $sql .= ' ORDER BY id LIMIT :limit';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function markDeltaQueueStatus(int $id, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE delta_queue SET status=:status, updated_at=CURRENT_TIMESTAMP WHERE id=:id');
        $stmt->execute(['id' => $id, 'status' => $status]);
    }

    public function saveCutoverReport(string $jobId, string $status, array $report): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO cutover_reports(job_id, status, report_json) VALUES(:job_id,:status,:report_json)');
        $stmt->execute([
            'job_id' => $jobId,
            'status' => $status,
            'report_json' => (string) json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function latestCutoverReport(string $jobId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT report_json FROM cutover_reports WHERE job_id=:job_id ORDER BY id DESC LIMIT 1');
        $stmt->execute(['job_id' => $jobId]);
        $raw = $stmt->fetchColumn();
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
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

    public function saveSchemaSnapshot(string $jobId, string $schemaVersion, string $runtimeMode, array $snapshot): void
    {
        $this->assertJobExists($jobId);
        $stmt = $this->pdo->prepare('INSERT INTO schema_snapshots(job_id, schema_version, runtime_mode, snapshot_json) VALUES(:job_id,:schema_version,:runtime_mode,:snapshot_json)');
        $stmt->execute([
            'job_id' => $jobId,
            'schema_version' => $schemaVersion,
            'runtime_mode' => $runtimeMode,
            'snapshot_json' => (string) json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /** @return array<string,mixed>|null */
    public function latestSchemaSnapshot(string $jobId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT snapshot_json FROM schema_snapshots WHERE job_id=:job_id ORDER BY id DESC LIMIT 1');
        $stmt->execute(['job_id' => $jobId]);
        $raw = $stmt->fetchColumn();
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function saveEntityGraph(string $jobId, array $graph): void
    {
        $this->assertJobExists($jobId);
        $stmt = $this->pdo->prepare('INSERT INTO entity_graph(job_id, graph_json) VALUES(:job_id,:graph_json)');
        $stmt->execute([
            'job_id' => $jobId,
            'graph_json' => (string) json_encode($graph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /** @return array<string,mixed>|null */
    public function entityGraph(string $jobId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT graph_json FROM entity_graph WHERE job_id=:job_id ORDER BY id DESC LIMIT 1');
        $stmt->execute(['job_id' => $jobId]);
        $raw = $stmt->fetchColumn();
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /** @param array<string,mixed> $boundaries */
    public function saveExtractProgress(string $jobId, string $entityType, string $tableName, string $strategy, int $batchSize, int $rowsRead, array $boundaries): void
    {
        $this->assertJobExists($jobId);
        $stmt = $this->pdo->prepare('INSERT INTO extract_progress(job_id, entity_type, table_name, strategy, batch_size, rows_read, boundaries_json) VALUES(:job_id,:entity_type,:table_name,:strategy,:batch_size,:rows_read,:boundaries_json)');
        $stmt->execute([
            'job_id' => $jobId,
            'entity_type' => $entityType,
            'table_name' => $tableName,
            'strategy' => $strategy,
            'batch_size' => $batchSize,
            'rows_read' => $rowsRead,
            'boundaries_json' => (string) json_encode($boundaries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function saveCursor(string $jobId, string $entityType, string $tableName, string $strategy, ?string $lastProcessedId, ?string $lastProcessedTimestamp, ?string $batchStart, ?string $batchEnd): void
    {
        $this->assertJobExists($jobId);
        $stmt = $this->pdo->prepare('INSERT OR REPLACE INTO cursors(job_id, entity_type, table_name, strategy, last_processed_id, last_processed_timestamp, batch_start, batch_end, updated_at) VALUES(:job_id,:entity_type,:table_name,:strategy,:last_processed_id,:last_processed_timestamp,:batch_start,:batch_end,CURRENT_TIMESTAMP)');
        $stmt->execute([
            'job_id' => $jobId,
            'entity_type' => $entityType,
            'table_name' => $tableName,
            'strategy' => $strategy,
            'last_processed_id' => $lastProcessedId,
            'last_processed_timestamp' => $lastProcessedTimestamp,
            'batch_start' => $batchStart,
            'batch_end' => $batchEnd,
        ]);
    }

    /** @return array<string,mixed>|null */
    public function cursor(string $jobId, string $entityType, string $tableName): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cursors WHERE job_id=:job_id AND entity_type=:entity_type AND table_name=:table_name LIMIT 1');
        $stmt->execute(['job_id' => $jobId, 'entity_type' => $entityType, 'table_name' => $tableName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** @return array<int,array<string,mixed>> */
    public function cursors(string $jobId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cursors WHERE job_id=:job_id ORDER BY entity_type, table_name');
        $stmt->execute(['job_id' => $jobId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveDbVerifyResult(string $jobId, string $verifyMode, array $result): void
    {
        $this->assertJobExists($jobId);
        $stmt = $this->pdo->prepare('INSERT INTO db_verify_results(job_id, verify_mode, result_json) VALUES(:job_id,:verify_mode,:result_json)');
        $stmt->execute([
            'job_id' => $jobId,
            'verify_mode' => $verifyMode,
            'result_json' => (string) json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }


    

    public function saveMigrationPlan(string $jobId, string $planId, string $planHash, string $configHash, array $plan): void
    {
        $this->pdo->prepare('INSERT OR REPLACE INTO migration_jobs(job_id, plan_id, mode, status, updated_at) VALUES(:job_id,:plan_id,:mode,:status,CURRENT_TIMESTAMP)')
            ->execute(['job_id' => $jobId, 'plan_id' => $planId, 'mode' => 'deterministic', 'status' => 'planned']);

        $this->pdo->prepare('INSERT OR REPLACE INTO migration_plans(plan_id, job_id, plan_hash, config_hash, plan_json, created_at) VALUES(:plan_id,:job_id,:plan_hash,:config_hash,:plan_json,CURRENT_TIMESTAMP)')
            ->execute([
                'plan_id' => $planId,
                'job_id' => $jobId,
                'plan_hash' => $planHash,
                'config_hash' => $configHash,
                'plan_json' => (string) json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
    }

    public function latestPlan(string $jobId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT plan_json FROM migration_plans WHERE job_id=:job_id ORDER BY created_at DESC LIMIT 1');
        $stmt->execute(['job_id' => $jobId]);
        $raw = $stmt->fetchColumn();

        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function saveExecutionBatch(string $jobId, string $planId, string $batchId, string $phase, string $entityType, int $stableOrder, string $status): void
    {
        $this->pdo->prepare('INSERT OR REPLACE INTO execution_batches(batch_id, job_id, plan_id, phase, entity_type, stable_order, status, updated_at) VALUES(:batch_id,:job_id,:plan_id,:phase,:entity_type,:stable_order,:status,CURRENT_TIMESTAMP)')
            ->execute([
                'batch_id' => $batchId,
                'job_id' => $jobId,
                'plan_id' => $planId,
                'phase' => $phase,
                'entity_type' => $entityType,
                'stable_order' => $stableOrder,
                'status' => $status,
            ]);
    }

    public function saveExecutionStep(array $record): void
    {
        $this->pdo->prepare('INSERT OR REPLACE INTO execution_steps(step_id, job_id, plan_id, phase, batch_id, entity_type, source_id, reserved_target_id, actual_target_id, operation_type, payload_hash, status, attempt_count, verification_status, error_class, error_code, diagnostic_blob, started_at, finished_at) VALUES(:step_id,:job_id,:plan_id,:phase,:batch_id,:entity_type,:source_id,:reserved_target_id,:actual_target_id,:operation_type,:payload_hash,:status,:attempt_count,:verification_status,:error_class,:error_code,:diagnostic_blob,:started_at,:finished_at)')
            ->execute($record);
    }

    public function saveFailureEvent(array $record): void
    {
        $this->pdo->prepare('INSERT INTO failure_events(job_id, plan_id, phase, batch_id, entity_type, source_id, classification, error_code, diagnostic_blob, retryable) VALUES(:job_id,:plan_id,:phase,:batch_id,:entity_type,:source_id,:classification,:error_code,:diagnostic_blob,:retryable)')
            ->execute($record);
    }

    public function saveIdReservation(array $record): void
    {
        $this->pdo->prepare('INSERT OR REPLACE INTO id_reservations(plan_id, entity_type, source_id, requested_target_id, reserved_target_id, policy, reason, updated_at) VALUES(:plan_id,:entity_type,:source_id,:requested_target_id,:reserved_target_id,:policy,:reason,CURRENT_TIMESTAMP)')
            ->execute($record);
    }

    public function findIdReservation(string $planId, string $entityType, string $sourceId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM id_reservations WHERE plan_id=:plan_id AND entity_type=:entity_type AND source_id=:source_id LIMIT 1');
        $stmt->execute(['plan_id' => $planId, 'entity_type' => $entityType, 'source_id' => $sourceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function saveRelationMap(string $planId, string $relationKey, string $ownerEntityType, string $ownerSourceId, string $targetEntityType, string $targetSourceId, ?string $targetResolvedId, string $status, ?string $reason): void
    {
        $this->pdo->prepare('INSERT OR REPLACE INTO relation_map(plan_id, relation_key, owner_entity_type, owner_source_id, target_entity_type, target_source_id, target_resolved_id, status, reason) VALUES(:plan_id,:relation_key,:owner_entity_type,:owner_source_id,:target_entity_type,:target_source_id,:target_resolved_id,:status,:reason)')
            ->execute([
                'plan_id' => $planId,
                'relation_key' => $relationKey,
                'owner_entity_type' => $ownerEntityType,
                'owner_source_id' => $ownerSourceId,
                'target_entity_type' => $targetEntityType,
                'target_source_id' => $targetSourceId,
                'target_resolved_id' => $targetResolvedId,
                'status' => $status,
                'reason' => $reason,
            ]);
    }

    public function saveFileTransferMap(array $record): void
    {
        $this->pdo->prepare('INSERT INTO file_transfer_map(plan_id, source_file_id, source_path, source_checksum, source_size, target_file_id, target_path, target_checksum, target_size, relation_key, status, resume_token, updated_at) VALUES(:plan_id,:source_file_id,:source_path,:source_checksum,:source_size,:target_file_id,:target_path,:target_checksum,:target_size,:relation_key,:status,:resume_token,CURRENT_TIMESTAMP)')
            ->execute($record);
    }

    public function saveCheckpointState(string $jobId, string $planId, string $phase, ?string $cursor, array $payload): void
    {
        $this->pdo->prepare('INSERT OR REPLACE INTO checkpoint_state(job_id, plan_id, phase, cursor, payload_json, updated_at) VALUES(:job_id,:plan_id,:phase,:cursor,:payload_json,CURRENT_TIMESTAMP)')
            ->execute(['job_id' => $jobId, 'plan_id' => $planId, 'phase' => $phase, 'cursor' => $cursor, 'payload_json' => (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }

    public function listCheckpointState(string $jobId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM checkpoint_state WHERE job_id=:job_id ORDER BY phase');
        $stmt->execute(['job_id' => $jobId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveReplayGuard(string $idempotencyKey, string $jobId, string $planId, string $phase, string $entityType, string $sourceId, string $payloadHash, string $status): void
    {
        $this->pdo->prepare('INSERT OR REPLACE INTO replay_guard(idempotency_key, job_id, plan_id, phase, entity_type, source_id, payload_hash, status) VALUES(:idempotency_key,:job_id,:plan_id,:phase,:entity_type,:source_id,:payload_hash,:status)')
            ->execute([
                'idempotency_key' => $idempotencyKey,
                'job_id' => $jobId,
                'plan_id' => $planId,
                'phase' => $phase,
                'entity_type' => $entityType,
                'source_id' => $sourceId,
                'payload_hash' => $payloadHash,
                'status' => $status,
            ]);
    }

    public function replayGuardStatus(string $idempotencyKey): ?string
    {
        $stmt = $this->pdo->prepare('SELECT status FROM replay_guard WHERE idempotency_key=:idempotency_key LIMIT 1');
        $stmt->execute(['idempotency_key' => $idempotencyKey]);
        $status = $stmt->fetchColumn();

        return is_string($status) ? $status : null;
    }

    public function upsertSyncState(array $record): void
    {
        $this->pdo->prepare('INSERT OR REPLACE INTO sync_state(sync_id, job_id, entity_type, source_id, target_id, direction, last_synced_at, last_hash, source_version, target_version, sync_state, mode, updated_at) VALUES(:sync_id,:job_id,:entity_type,:source_id,:target_id,:direction,:last_synced_at,:last_hash,:source_version,:target_version,:sync_state,:mode,CURRENT_TIMESTAMP)')
            ->execute($record);
    }

    public function recordSyncConflict(string $conflictId, ?string $syncId, string $jobId, string $entityType, string $sourceId, ?string $targetId, string $conflictType, string $resolutionStrategy, array $payload): void
    {
        $this->pdo->prepare('INSERT OR REPLACE INTO sync_conflicts(conflict_id, sync_id, job_id, entity_type, source_id, target_id, conflict_type, resolution_strategy, conflict_payload, status, created_at) VALUES(:conflict_id,:sync_id,:job_id,:entity_type,:source_id,:target_id,:conflict_type,:resolution_strategy,:conflict_payload,"open",CURRENT_TIMESTAMP)')
            ->execute([
                'conflict_id' => $conflictId,
                'sync_id' => $syncId,
                'job_id' => $jobId,
                'entity_type' => $entityType,
                'source_id' => $sourceId,
                'target_id' => $targetId,
                'conflict_type' => $conflictType,
                'resolution_strategy' => $resolutionStrategy,
                'conflict_payload' => (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
    }

    public function resolveSyncConflict(string $conflictId, string $strategy, array $payload): void
    {
        $this->pdo->prepare('UPDATE sync_conflicts SET resolution_strategy=:strategy, resolution_payload=:resolution_payload, status="resolved", resolved_at=CURRENT_TIMESTAMP WHERE conflict_id=:conflict_id')
            ->execute([
                'conflict_id' => $conflictId,
                'strategy' => $strategy,
                'resolution_payload' => (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
    }

    /** @return array<int,array<string,mixed>> */
    public function listSyncConflicts(string $jobId, ?string $status = null): array
    {
        $sql = 'SELECT * FROM sync_conflicts WHERE job_id=:job_id';
        $params = ['job_id' => $jobId];
        if ($status !== null) {
            $sql .= ' AND status=:status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY created_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function recordSyncDrift(string $driftId, string $jobId, string $entityType, ?string $sourceId, ?string $targetId, string $category, string $severity, array $payload): void
    {
        $this->pdo->prepare('INSERT OR REPLACE INTO sync_drift(drift_id, job_id, entity_type, source_id, target_id, drift_category, severity, drift_payload, status, detected_at) VALUES(:drift_id,:job_id,:entity_type,:source_id,:target_id,:drift_category,:severity,:drift_payload,"open",CURRENT_TIMESTAMP)')
            ->execute([
                'drift_id' => $driftId,
                'job_id' => $jobId,
                'entity_type' => $entityType,
                'source_id' => $sourceId,
                'target_id' => $targetId,
                'drift_category' => $category,
                'severity' => $severity,
                'drift_payload' => (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
    }

    /** @return array<int,array<string,mixed>> */
    public function listSyncDrift(string $jobId, ?string $status = null): array
    {
        $sql = 'SELECT * FROM sync_drift WHERE job_id=:job_id';
        $params = ['job_id' => $jobId];
        if ($status !== null) {
            $sql .= ' AND status=:status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY detected_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function appendSyncLedger(array $record): void
    {
        $this->pdo->prepare('INSERT OR REPLACE INTO sync_ledger(sync_id, ledger_id, job_id, entity_type, source_id, target_id, action, direction, checksum_before, checksum_after, metadata_json, created_at) VALUES(:sync_id,:ledger_id,:job_id,:entity_type,:source_id,:target_id,:action,:direction,:checksum_before,:checksum_after,:metadata_json,CURRENT_TIMESTAMP)')
            ->execute($record);
    }

    /** @return array<int,array<string,mixed>> */
    public function syncLedger(string $jobId, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sync_ledger WHERE job_id=:job_id ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue(':job_id', $jobId);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string,mixed> $metrics */
    public function recordSyncMetric(string $jobId, array $metrics): void
    {
        $row = $this->latestSyncMetric($jobId) ?? [];
        $defaults = [
            'service_state' => 'running',
            'sync_operations_total' => 0,
            'sync_errors_total' => 0,
            'sync_conflicts_total' => 0,
            'sync_drift_total' => 0,
            'replication_lag_seconds' => 0,
            'queue_backlog' => 0,
            'sync_health_score' => 1,
        ];
        $payload = array_merge($defaults, $row, $metrics);

        $this->pdo->prepare('INSERT INTO sync_metrics(job_id, service_state, sync_operations_total, sync_errors_total, sync_conflicts_total, sync_drift_total, replication_lag_seconds, queue_backlog, sync_health_score, measured_at) VALUES(:job_id,:service_state,:sync_operations_total,:sync_errors_total,:sync_conflicts_total,:sync_drift_total,:replication_lag_seconds,:queue_backlog,:sync_health_score,CURRENT_TIMESTAMP)')
            ->execute([
                'job_id' => $jobId,
                'service_state' => $payload['service_state'],
                'sync_operations_total' => (int) $payload['sync_operations_total'],
                'sync_errors_total' => (int) $payload['sync_errors_total'],
                'sync_conflicts_total' => (int) $payload['sync_conflicts_total'],
                'sync_drift_total' => (int) $payload['sync_drift_total'],
                'replication_lag_seconds' => (int) $payload['replication_lag_seconds'],
                'queue_backlog' => (int) $payload['queue_backlog'],
                'sync_health_score' => (float) $payload['sync_health_score'],
            ]);
    }

    /** @return array<string,mixed>|null */
    public function latestSyncMetric(string $jobId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sync_metrics WHERE job_id=:job_id ORDER BY id DESC LIMIT 1');
        $stmt->execute(['job_id' => $jobId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** @return array<string,mixed> */
    public function summary(string $jobId): array
    {
        $tables = ['queue', 'entity_map', 'diff', 'integrity_issues', 'logs', 'execution_batches', 'execution_steps', 'failure_events', 'verification_results'];
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
