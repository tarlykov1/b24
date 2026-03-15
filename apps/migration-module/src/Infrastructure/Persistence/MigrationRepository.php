<?php

declare(strict_types=1);

namespace MigrationModule\Infrastructure\Persistence;

use DateTimeImmutable;
use DomainException;
use MigrationModule\Domain\Job\JobLifecycle;

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

    /** @var array<string,array<string,mixed>> */
    private array $snapshotState = [];

    /** @var array<string,array<int,array<string,mixed>>> */
    private array $reconciliationQueue = [];

    /** @var array<string,array<int,array<string,mixed>>> */
    private array $conflicts = [];

    /** @var array<string,array<string,mixed>> */
    private array $entityStates = [];

    /** @var array<string,array<string,mixed>> */
    private array $targetChangeMarkers = [];

    /** @var array<string,array<string,array<string,mixed>>> */
    private array $identityMappings = [];

    /** @var array<string,array<string,mixed>> */
    private array $queueStates = [];

    /** @var array<string,string> */
    private array $highWaterMarks = [];

    /** @var array<string,array<string,mixed>> */
    private array $distributedStates = [];

    public function beginJob(string $mode): string
    {
        $jobId = sprintf('job-%s', bin2hex(random_bytes(4)));
        $this->jobs[$jobId] = [
            'id' => $jobId,
            'mode' => $mode,
            'status' => JobLifecycle::CREATED,
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
        $this->assertJobExists($jobId);
        $this->snapshots[$jobId]['source'] = $source;
    }

    /** @param array<string, array<int, array<string, mixed>>> $target */
    public function setTargetSnapshot(string $jobId, array $target): void
    {
        $this->assertJobExists($jobId);
        $this->snapshots[$jobId]['target'] = $target;
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    public function sourceSnapshot(string $jobId): array
    {
        $this->assertJobExists($jobId);
        return $this->snapshots[$jobId]['source'] ?? [];
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    public function targetSnapshot(string $jobId): array
    {
        $this->assertJobExists($jobId);
        return $this->snapshots[$jobId]['target'] ?? [];
    }

    public function saveMapping(string $jobId, string $entityType, int|string $oldId, int|string $newId): void
    {
        $this->assertJobExists($jobId);
        $this->mappings[$jobId][sprintf('%s:%s', $entityType, (string) $oldId)] = (string) $newId;
    }

    public function findMappedId(string $jobId, string $entityType, int|string $oldId): ?string
    {
        $this->assertJobExists($jobId);
        return $this->mappings[$jobId][sprintf('%s:%s', $entityType, (string) $oldId)] ?? null;
    }

    /** @return array<string, string> */
    public function mappings(string $jobId): array
    {
        $this->assertJobExists($jobId);
        return $this->mappings[$jobId] ?? [];
    }

    /** @param array<string, mixed> $report */
    public function saveReport(string $jobId, array $report): void
    {
        $this->assertJobExists($jobId);
        $this->reports[$jobId][] = $report;
    }

    /** @return array<int, array<string, mixed>> */
    public function reports(string $jobId): array
    {
        $this->assertJobExists($jobId);
        return $this->reports[$jobId] ?? [];
    }

    /** @param array<string, mixed> $checkpoint */
    public function saveCheckpoint(string $jobId, array $checkpoint): void
    {
        $this->assertJobExists($jobId);
        $this->checkpoints[$jobId][] = $checkpoint;
    }

    /** @return array<string, mixed>|null */
    public function latestCheckpoint(string $jobId): ?array
    {
        $this->assertJobExists($jobId);
        $checkpoints = $this->checkpoints[$jobId] ?? [];

        return $checkpoints === [] ? null : $checkpoints[array_key_last($checkpoints)];
    }

    public function markDestructiveConfirmed(string $jobId): void
    {
        $this->assertJobExists($jobId);
        $this->jobs[$jobId]['destructive_confirmed'] = true;
    }

    public function isDestructiveConfirmed(string $jobId): bool
    {
        $this->assertJobExists($jobId);
        return (bool) ($this->jobs[$jobId]['destructive_confirmed'] ?? false);
    }

    /** @param array<string, mixed> $log */
    public function appendChangeLog(string $jobId, array $log): void
    {
        $this->assertJobExists($jobId);
        $this->jobs[$jobId]['change_log'][] = $log;
    }

    /** @return array<int, array<string, mixed>> */
    public function changeLog(string $jobId): array
    {
        $this->assertJobExists($jobId);
        return $this->jobs[$jobId]['change_log'] ?? [];
    }


    /** @param array<string,mixed> $decision */
    public function saveOperatorDecision(string $jobId, array $decision): void
    {
        $this->assertJobExists($jobId);
        $this->operatorDecisions[$jobId][] = $decision;
    }

    /** @return array<int,array<string,mixed>> */
    public function operatorDecisions(string $jobId): array
    {
        $this->assertJobExists($jobId);
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
        $this->assertJobExists($jobId);
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
        $this->assertJobExists($jobId);
        return $this->identityMappings[$jobId][sprintf('%s:%s', $entityType, (string) $sourceId)] ?? null;
    }

    /** @return array<string,array<string,mixed>> */
    public function identityMappings(string $jobId): array
    {
        $this->assertJobExists($jobId);
        return $this->identityMappings[$jobId] ?? [];
    }

    /** @param array<string,mixed> $state */
    public function saveQueueState(string $jobId, string $queueName, array $state): void
    {
        $this->assertJobExists($jobId);
        $this->queueStates[$jobId . ':' . $queueName] = $state;
    }

    /** @return array<string,mixed>|null */
    public function queueState(string $jobId, string $queueName): ?array
    {
        $this->assertJobExists($jobId);
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

    /** @param array<string,mixed> $state */
    public function saveDistributedState(string $jobId, array $state): void
    {
        $this->assertJobExists($jobId);
        $this->distributedStates[$jobId] = $state;
    }

    /** @return array<string,mixed>|null */
    public function distributedState(string $jobId): ?array
    {
        $this->assertJobExists($jobId);
        return $this->distributedStates[$jobId] ?? null;
    }

    public function hasJob(string $jobId): bool
    {
        return array_key_exists($jobId, $this->jobs);
    }

    /** @return array<string,mixed> */
    public function job(string $jobId): array
    {
        if (!$this->hasJob($jobId)) {
            throw new DomainException((string) json_encode([
                'error' => 'job_not_found',
                'job_id' => $jobId,
                'message' => 'Requested job-id does not exist.',
            ], JSON_UNESCAPED_UNICODE));
        }

        return $this->jobs[$jobId];
    }

    public function assertJobExists(string $jobId): void
    {
        $this->job($jobId);
    }

    public function setJobStatus(string $jobId, string $status): void
    {
        $job = $this->job($jobId);
        $current = (string) ($job['status'] ?? JobLifecycle::CREATED);
        if ($current !== $status && !JobLifecycle::canTransition($current, $status)) {
            throw new DomainException((string) json_encode([
                'error' => 'invalid_job_transition',
                'job_id' => $jobId,
                'from' => $current,
                'to' => $status,
                'allowed' => JobLifecycle::transitions()[$current] ?? [],
            ], JSON_UNESCAPED_UNICODE));
        }

        $this->jobs[$jobId]['status'] = $status;
        if (in_array($status, [JobLifecycle::COMPLETED, JobLifecycle::CANCELLED, JobLifecycle::FAILED], true)) {
            $this->jobs[$jobId]['ended_at'] = new DateTimeImmutable();
        }
    }


    public function updateJobStatus(string $jobId, string $status): void
    {
        $this->setJobStatus($jobId, $status);
    }

    public function jobStatus(string $jobId): ?string
    {
        return $this->job($jobId)['status'] ?? null;
    }


    public function clearMappings(string $jobId): void
    {
        $this->assertJobExists($jobId);
        $this->mappings[$jobId] = [];
    }

    public function clearCheckpoints(string $jobId): void
    {
        $this->assertJobExists($jobId);
        $this->checkpoints[$jobId] = [];
    }

    /** @param array<string,mixed> $record */
    public function appendHealingAuditLog(string $jobId, array $record): void
    {
        $this->assertJobExists($jobId);
        $this->healingAuditLog[$jobId][] = $record;
    }

    /** @return array<int,array<string,mixed>> */
    public function healingAuditLog(string $jobId): array
    {
        $this->assertJobExists($jobId);
        return $this->healingAuditLog[$jobId] ?? [];
    }

    /** @param array<string,mixed> $item */
    public function addQuarantineItem(string $jobId, array $item): void
    {
        $this->assertJobExists($jobId);
        $this->quarantine[$jobId][] = $item;
    }

    /** @return array<int,array<string,mixed>> */
    public function quarantineItems(string $jobId): array
    {
        $this->assertJobExists($jobId);
        return $this->quarantine[$jobId] ?? [];
    }

    /** @param array<string,mixed> $item */
    public function addRetryItem(string $jobId, array $item): void
    {
        $this->assertJobExists($jobId);
        $this->retryQueue[$jobId][] = $item;
    }

    /** @return array<int,array<string,mixed>> */
    public function retryItems(string $jobId): array
    {
        $this->assertJobExists($jobId);
        return $this->retryQueue[$jobId] ?? [];
    }

    /** @param array<string,mixed> $item */
    public function addDeadLetterItem(string $jobId, array $item): void
    {
        $this->assertJobExists($jobId);
        $this->deadLetterQueue[$jobId][] = $item;
    }

    /** @return array<int,array<string,mixed>> */
    public function deadLetterItems(string $jobId): array
    {
        $this->assertJobExists($jobId);
        return $this->deadLetterQueue[$jobId] ?? [];
    }

    public function incrementHealingAttempt(string $jobId, string $categoryKey, string $entityId): int
    {
        $this->assertJobExists($jobId);
        $key = sprintf('%s:%s', $categoryKey, $entityId);
        $this->healingAttempts[$jobId][$key] = ($this->healingAttempts[$jobId][$key] ?? 0) + 1;

        return $this->healingAttempts[$jobId][$key];
    }

    /** @param array<string,mixed> $override */
    public function saveManualOverride(string $jobId, string $overrideKey, array $override): void
    {
        $this->assertJobExists($jobId);
        $this->manualOverrides[$jobId . ':' . $overrideKey] = $override;
    }

    /** @return array<string,mixed>|null */
    public function manualOverride(string $jobId, string $overrideKey): ?array
    {
        $this->assertJobExists($jobId);
        return $this->manualOverrides[$jobId . ':' . $overrideKey] ?? null;
    }

    /** @param array<string,mixed> $snapshot */
    public function saveSnapshot(string $jobId, array $snapshot): void
    {
        $this->assertJobExists($jobId);
        $this->snapshotState[$jobId] = $snapshot;
    }

    /** @return array<string,mixed>|null */
    public function snapshot(string $jobId): ?array
    {
        $this->assertJobExists($jobId);
        return $this->snapshotState[$jobId] ?? null;
    }

    /** @param array<string,mixed> $item */
    public function enqueueReconciliationItem(string $jobId, array $item): void
    {
        $this->assertJobExists($jobId);
        $this->reconciliationQueue[$jobId][] = $item;
    }

    /** @return array<int,array<string,mixed>> */
    public function reconciliationQueue(string $jobId): array
    {
        $this->assertJobExists($jobId);
        return $this->reconciliationQueue[$jobId] ?? [];
    }

    /** @param array<string,mixed> $conflict */
    public function addConflict(string $jobId, array $conflict): void
    {
        $this->assertJobExists($jobId);
        $this->conflicts[$jobId][] = $conflict;
    }

    /** @return array<int,array<string,mixed>> */
    public function conflicts(string $jobId): array
    {
        $this->assertJobExists($jobId);
        return $this->conflicts[$jobId] ?? [];
    }

    /** @param array<string,mixed> $state */
    public function saveEntityState(string $jobId, string $entityType, string $entityId, array $state): void
    {
        $this->assertJobExists($jobId);
        $this->entityStates[sprintf('%s:%s:%s', $jobId, $entityType, $entityId)] = $state;
    }

    /** @return array<string,mixed>|null */
    public function entityState(string $jobId, string $entityType, string $entityId): ?array
    {
        $this->assertJobExists($jobId);
        return $this->entityStates[sprintf('%s:%s:%s', $jobId, $entityType, $entityId)] ?? null;
    }

    /** @param array<string,mixed> $marker */
    public function saveTargetChangeMarker(string $jobId, string $entityType, string $targetId, array $marker): void
    {
        $this->assertJobExists($jobId);
        $this->targetChangeMarkers[sprintf('%s:%s:%s', $jobId, $entityType, $targetId)] = $marker;
    }

    /** @return array<string,mixed>|null */
    public function targetChangeMarker(string $jobId, string $entityType, string $targetId): ?array
    {
        $this->assertJobExists($jobId);
        return $this->targetChangeMarkers[sprintf('%s:%s:%s', $jobId, $entityType, $targetId)] ?? null;
    }

}
