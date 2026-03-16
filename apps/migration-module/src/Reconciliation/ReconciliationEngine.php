<?php

declare(strict_types=1);

namespace MigrationModule\Reconciliation;

use MigrationModule\Prototype\Adapter\SourceAdapterInterface;
use MigrationModule\Prototype\Adapter\TargetAdapterInterface;
use MigrationModule\Prototype\Storage\SqliteStorage;

final class ReconciliationEngine
{
    private const STATUS_OK = 'OK';

    public function __construct(
        private readonly SqliteStorage $storage,
        private readonly SourceAdapterInterface $source,
        private readonly TargetAdapterInterface $target,
    ) {
    }

    public function reconcile(string $jobId, string $entity, string $strategy, int $limit, int $offset, float $sampleRatio, int $rateLimit): array
    {
        $rows = $this->source->fetch($entity, $offset, $limit);
        if ($sampleRatio > 0 && $sampleRatio < 1) {
            $sampleSize = max(1, (int) floor(count($rows) * $sampleRatio));
            $rows = array_slice($rows, 0, $sampleSize);
        }

        $checks = ['processed' => 0, 'ok' => 0, 'mismatches' => 0, 'statuses' => []];
        foreach ($rows as $row) {
            $sourceId = (string) ($row['id'] ?? '');
            if ($sourceId === '') {
                continue;
            }

            $checks['processed']++;
            $mapped = $this->storage->findMapping($entity, $sourceId);
            $targetId = (string) ($mapped['target_id'] ?? $sourceId);
            $exists = $this->target->exists($entity, $targetId);

            if (!$exists) {
                $this->record($jobId, $entity, $sourceId, 'MISSING_IN_TARGET', 'missing', ['target_id' => $targetId], 'high');
                $checks['mismatches']++;
                $checks['statuses']['MISSING_IN_TARGET'] = ($checks['statuses']['MISSING_IN_TARGET'] ?? 0) + 1;
                $this->throttle($rateLimit);
                continue;
            }

            $diff = $this->diffByStrategy($strategy, $row);
            if ($diff === []) {
                $this->record($jobId, $entity, $sourceId, self::STATUS_OK, 'none', null, 'none');
                $checks['ok']++;
                $checks['statuses'][self::STATUS_OK] = ($checks['statuses'][self::STATUS_OK] ?? 0) + 1;
            } else {
                $status = $diff['status'];
                $this->record($jobId, $entity, $sourceId, $status, $diff['type'], $diff['details'], $diff['severity']);
                $checks['mismatches']++;
                $checks['statuses'][$status] = ($checks['statuses'][$status] ?? 0) + 1;
            }

            $this->throttle($rateLimit);
        }

        return ['job_id' => $jobId, 'entity' => $entity, 'strategy' => $strategy] + $checks;
    }

    private function record(string $jobId, string $entity, string $entityId, string $status, string $type, ?array $details, string $severity): void
    {
        $this->storage->saveReconciliationResult(
            $jobId,
            $entity,
            $entityId,
            $status,
            $type,
            $details === null ? null : (string) json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $severity,
        );
    }

    private function diffByStrategy(string $strategy, array $row): array
    {
        $fieldMismatch = isset($row['simulate_mismatch']) && (bool) $row['simulate_mismatch'] === true;
        $relationBroken = isset($row['simulate_relation_broken']) && (bool) $row['simulate_relation_broken'] === true;
        $fileHashMismatch = isset($row['simulate_file_hash_mismatch']) && (bool) $row['simulate_file_hash_mismatch'] === true;

        if ($strategy === 'fast') {
            if ($fieldMismatch) {
                return ['status' => 'FIELD_MISMATCH', 'type' => 'timestamp_or_count', 'details' => ['timestamp' => $row['updated_at'] ?? null], 'severity' => 'medium'];
            }

            return [];
        }

        if ($strategy === 'balanced') {
            if ($relationBroken) {
                return ['status' => 'RELATION_BROKEN', 'type' => 'relation_integrity', 'details' => ['relation' => 'owner/ref'], 'severity' => 'high'];
            }
            if ($fieldMismatch) {
                return ['status' => 'FIELD_MISMATCH', 'type' => 'key_fields', 'details' => ['fields' => ['title', 'status']], 'severity' => 'medium'];
            }

            return [];
        }

        if ($fileHashMismatch) {
            return ['status' => 'FILE_HASH_MISMATCH', 'type' => 'attachment_hash', 'details' => ['checksum' => 'different'], 'severity' => 'high'];
        }
        if ($relationBroken) {
            return ['status' => 'RELATION_BROKEN', 'type' => 'comment_activity_relation', 'details' => ['comments' => 'count_mismatch'], 'severity' => 'high'];
        }
        if ($fieldMismatch) {
            return ['status' => 'FIELD_MISMATCH', 'type' => 'full_field_diff', 'details' => ['diff' => ['title' => 'changed']], 'severity' => 'medium'];
        }

        return [];
    }

    private function throttle(int $rateLimit): void
    {
        if ($rateLimit <= 0) {
            return;
        }
        usleep((int) floor(1_000_000 / $rateLimit));
    }
}
