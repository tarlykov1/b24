<?php

declare(strict_types=1);

namespace MigrationModule\Infrastructure\Persistence;

use DateTimeImmutable;

final class MigrationRepository
{
    /** @var array<string, array<string, mixed>> */
    private array $jobs = [];

    /** @var array<string, array<string, array<int, array<string, mixed>>>> */
    private array $snapshots = [];

    /** @var array<string, array<string, string>> */
    private array $mappings = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $reports = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $checkpoints = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $operatorDecisions = [];

    /** @var array<string, string> */
    private array $syncCheckpoints = [];

    /** @var array<string,array<int,array<string,mixed>>> */
    private array $healingAuditLog = [];

    /** @var array<string,array<int,array<string,mixed>>> */
    private array $quarantine = [];

    /** @var array<string,array<int,array<string,mixed>>> */
    private array $retryQueue = [];

    /** @var array<string,array<int,array<string,mixed>>> */
    private array $deadLetterQueue = [];

    /** @var array<string,array<string,int>> */
    private array $healingAttempts = [];

    /** @var array<string,array<string,mixed>> */
    private array $manualOverrides = [];

    public function beginJob(string $mode): string
    {
        $jobId = sprintf('job-%s', bin2hex(random_bytes(4)));
        $this->jobs[$jobId] = [
            'id' => $jobId,
            'mode' => $mode,
            'status' => 'ready',
            'started_at' => new DateTimeImmutable(),
            'ended_at' => null,
            'metrics' => [
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => 0,
                'batch_avg_ms' => 0.0,
                'api_requests' => 0,
                'retries' => 0,
            ],
            'destructive_confirmed' => false,
            'change_log' => [],
        ];

        return $jobId;
    }

    /** @param array<string, array<int, array<string, mixed>>> $source */
    public function setSourceSnapshot(string $jobId, array $source): void
    {
        $this->snapshots[$jobId]['source'] = $source;
    }

    /** @param array<string, array<int, array<string, mixed>>> $target */
    public function setTargetSnapshot(string $jobId, array $target): void
    {
        $this->snapshots[$jobId]['target'] = $target;
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    public function sourceSnapshot(string $jobId): array
    {
        return $this->snapshots[$jobId]['source'] ?? [];
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    public function targetSnapshot(string $jobId): array
    {
        return $this->snapshots[$jobId]['target'] ?? [];
    }

    public function saveMapping(string $jobId, string $entityType, int|string $oldId, int|string $newId): void
    {
        $this->mappings[$jobId][sprintf('%s:%s', $entityType, (string) $oldId)] = (string) $newId;
    }

    public function findMappedId(string $jobId, string $entityType, int|string $oldId): ?string
    {
        return $this->mappings[$jobId][sprintf('%s:%s', $entityType, (string) $oldId)] ?? null;
    }

    /** @return array<string, string> */
    public function mappings(string $jobId): array
    {
        return $this->mappings[$jobId] ?? [];
    }

    /** @param array<string, mixed> $report */
    public function saveReport(string $jobId, array $report): void
    {
        $this->reports[$jobId][] = $report;
    }

    /** @return array<int, array<string, mixed>> */
    public function reports(string $jobId): array
    {
        return $this->reports[$jobId] ?? [];
    }

    /** @param array<string, mixed> $checkpoint */
    public function saveCheckpoint(string $jobId, array $checkpoint): void
    {
        $this->checkpoints[$jobId][] = $checkpoint;
    }

    /** @return array<string, mixed>|null */
    public function latestCheckpoint(string $jobId): ?array
    {
        $checkpoints = $this->checkpoints[$jobId] ?? [];

        return $checkpoints === [] ? null : $checkpoints[array_key_last($checkpoints)];
    }

    public function markDestructiveConfirmed(string $jobId): void
    {
        $this->jobs[$jobId]['destructive_confirmed'] = true;
    }

    public function isDestructiveConfirmed(string $jobId): bool
    {
        return (bool) ($this->jobs[$jobId]['destructive_confirmed'] ?? false);
    }

    /** @param array<string, mixed> $log */
    public function appendChangeLog(string $jobId, array $log): void
    {
        $this->jobs[$jobId]['change_log'][] = $log;
    }

    /** @return array<int, array<string, mixed>> */
    public function changeLog(string $jobId): array
    {
        return $this->jobs[$jobId]['change_log'] ?? [];
    }


    /** @param array<string,mixed> $decision */
    public function saveOperatorDecision(string $jobId, array $decision): void
    {
        $this->operatorDecisions[$jobId][] = $decision;
    }

    /** @return array<int,array<string,mixed>> */
    public function operatorDecisions(string $jobId): array
    {
        return $this->operatorDecisions[$jobId] ?? [];
    }

    public function saveSyncCheckpoint(string $entityType, string $timestamp): void
    {
        $this->syncCheckpoints[$entityType] = $timestamp;
    }

    public function syncCheckpoint(string $entityType): ?string
    {
        return $this->syncCheckpoints[$entityType] ?? null;
    }

    /** @param array<string,mixed> $meta */
    public function saveIdentityMapping(
        string $jobId,
        string $entityType,
        int|string $sourceId,
        int|string $targetId,
        string $matchMethod,
        string $migrationVersion,
        ?string $signature,
        ?string $lastSyncedAt,
        array $meta = [],
    ): void {
        $key = sprintf('%s:%s', $entityType, (string) $sourceId);
        $this->identityMappings[$jobId][$key] = [
            'entity_type' => $entityType,
            'source_id' => (string) $sourceId,
            'target_id' => (string) $targetId,
            'match_method' => $matchMethod,
            'migration_version' => $migrationVersion,
            'signature' => $signature,
            'last_synced_at' => $lastSyncedAt,
            'meta' => $meta,
        ];
    }

    /** @return array<string,mixed>|null */
    public function findIdentityMapping(string $jobId, string $entityType, int|string $sourceId): ?array
    {
        return $this->identityMappings[$jobId][sprintf('%s:%s', $entityType, (string) $sourceId)] ?? null;
    }

    /** @return array<string,array<string,mixed>> */
    public function identityMappings(string $jobId): array
    {
        return $this->identityMappings[$jobId] ?? [];
    }

    /** @param array<string,mixed> $state */
    public function saveQueueState(string $jobId, string $queueName, array $state): void
    {
        $this->queueStates[$jobId . ':' . $queueName] = $state;
    }

    /** @return array<string,mixed>|null */
    public function queueState(string $jobId, string $queueName): ?array
    {
        return $this->queueStates[$jobId . ':' . $queueName] ?? null;
    }

    public function saveHighWaterMark(string $entityType, string $value): void
    {
        $this->highWaterMarks[$entityType] = $value;
    }

    public function highWaterMark(string $entityType): ?string
    {
        return $this->highWaterMarks[$entityType] ?? null;
    }

    public function setJobStatus(string $jobId, string $status): void
    {
        $this->jobs[$jobId]['status'] = $status;
        if (in_array($status, ['COMPLETED', 'ROLLED_BACK', 'FAILED'], true)) {
            $this->jobs[$jobId]['ended_at'] = new DateTimeImmutable();
        }
    }


    public function updateJobStatus(string $jobId, string $status): void
    {
        $this->setJobStatus($jobId, $status);
    }

    public function jobStatus(string $jobId): ?string
    {
        return $this->jobs[$jobId]['status'] ?? null;
    }

    public function clearMappings(string $jobId): void
    {
        $this->mappings[$jobId] = [];
    }

    public function clearCheckpoints(string $jobId): void
    {
        $this->checkpoints[$jobId] = [];
    }

    /** @param array<string,mixed> $record */
    public function appendHealingAuditLog(string $jobId, array $record): void
    {
        $this->healingAuditLog[$jobId][] = $record;
    }

    /** @return array<int,array<string,mixed>> */
    public function healingAuditLog(string $jobId): array
    {
        return $this->healingAuditLog[$jobId] ?? [];
    }

    /** @param array<string,mixed> $item */
    public function addQuarantineItem(string $jobId, array $item): void
    {
        $this->quarantine[$jobId][] = $item;
    }

    /** @return array<int,array<string,mixed>> */
    public function quarantineItems(string $jobId): array
    {
        return $this->quarantine[$jobId] ?? [];
    }

    /** @param array<string,mixed> $item */
    public function addRetryItem(string $jobId, array $item): void
    {
        $this->retryQueue[$jobId][] = $item;
    }

    /** @return array<int,array<string,mixed>> */
    public function retryItems(string $jobId): array
    {
        return $this->retryQueue[$jobId] ?? [];
    }

    /** @param array<string,mixed> $item */
    public function addDeadLetterItem(string $jobId, array $item): void
    {
        $this->deadLetterQueue[$jobId][] = $item;
    }

    /** @return array<int,array<string,mixed>> */
    public function deadLetterItems(string $jobId): array
    {
        return $this->deadLetterQueue[$jobId] ?? [];
    }

    public function incrementHealingAttempt(string $jobId, string $categoryKey, string $entityId): int
    {
        $key = sprintf('%s:%s', $categoryKey, $entityId);
        $this->healingAttempts[$jobId][$key] = ($this->healingAttempts[$jobId][$key] ?? 0) + 1;

        return $this->healingAttempts[$jobId][$key];
    }

    /** @param array<string,mixed> $override */
    public function saveManualOverride(string $jobId, string $overrideKey, array $override): void
    {
        $this->manualOverrides[$jobId . ':' . $overrideKey] = $override;
    }

    /** @return array<string,mixed>|null */
    public function manualOverride(string $jobId, string $overrideKey): ?array
    {
        return $this->manualOverrides[$jobId . ':' . $overrideKey] ?? null;
    }

}
