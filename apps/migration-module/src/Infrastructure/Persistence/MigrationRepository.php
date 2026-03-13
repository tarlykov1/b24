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

    /** @var array<string, array<int, array<string,mixed>>> */
    private array $autoMappingConfigs = [];

    /** @var array<string, array<string,string>> */
    private array $historicalFieldMappings = [];

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

    /** @param array<string,mixed> $config */
    public function saveAutoMappingConfig(string $jobId, array $config): int
    {
        $history = $this->autoMappingConfigs[$jobId] ?? [];
        $version = count($history) + 1;

        $this->autoMappingConfigs[$jobId][] = [
            'version' => $version,
            'created_at' => (new DateTimeImmutable())->format(DATE_ATOM),
            'origin' => 'auto_generated',
            'config' => $config,
        ];

        return $version;
    }

    /** @return array<int,array<string,mixed>> */
    public function autoMappingHistory(string $jobId): array
    {
        return $this->autoMappingConfigs[$jobId] ?? [];
    }

    public function rememberHistoricalFieldMapping(string $entityType, string $sourceField, string $targetField): void
    {
        $this->historicalFieldMappings[$entityType][$sourceField] = $targetField;
    }

    public function findHistoricalFieldMapping(string $sourceField, string $entityType): ?string
    {
        return $this->historicalFieldMappings[$entityType][$sourceField] ?? null;
    }

}
