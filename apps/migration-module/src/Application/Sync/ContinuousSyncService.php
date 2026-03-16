<?php

declare(strict_types=1);

namespace MigrationModule\Application\Sync;

use MigrationModule\Prototype\Storage\SqliteStorage;

final class ContinuousSyncService
{
    private const DEFAULT_ENTITIES = ['users', 'crm', 'tasks', 'files', 'comments', 'activity_logs'];

    public function __construct(private readonly SqliteStorage $storage)
    {
    }

    /** @param array<string,mixed> $config */
    public function start(string $jobId, array $config): array
    {
        $window = $config['sync']['window'] ?? ['start' => '01:00', 'end' => '05:00', 'timezone' => 'UTC'];
        $serviceMode = (string) ($config['sync']['mode'] ?? 'hybrid');
        $direction = (string) ($config['sync']['direction'] ?? 'source_to_target');

        $this->storage->setJobStatus($jobId, 'sync_running');
        $this->storage->recordSyncMetric($jobId, [
            'service_state' => 'running',
            'sync_operations_total' => 0,
            'sync_errors_total' => 0,
            'sync_conflicts_total' => 0,
            'sync_drift_total' => 0,
            'replication_lag_seconds' => 0,
            'queue_backlog' => 0,
            'sync_health_score' => 1.0,
        ]);

        return [
            'job_id' => $jobId,
            'service' => 'started',
            'mode' => $serviceMode,
            'direction' => $direction,
            'window' => $window,
            'entities' => $this->enabledEntities($config),
        ];
    }

    public function stop(string $jobId): array
    {
        $this->storage->setJobStatus($jobId, 'sync_stopped');
        $this->storage->recordSyncMetric($jobId, ['service_state' => 'stopped']);

        return ['job_id' => $jobId, 'service' => 'stopped'];
    }

    /** @param array<string,mixed> $config */
    public function status(string $jobId, array $config): array
    {
        $status = $this->storage->jobStatus($jobId) ?? 'unknown';
        $metrics = $this->storage->latestSyncMetric($jobId);

        return [
            'job_id' => $jobId,
            'sync_status' => $status,
            'mode' => (string) ($config['sync']['mode'] ?? 'hybrid'),
            'direction' => (string) ($config['sync']['direction'] ?? 'source_to_target'),
            'enabled_entities' => $this->enabledEntities($config),
            'metrics' => $metrics,
            'open_conflicts' => count($this->storage->listSyncConflicts($jobId, 'open')),
            'open_drift' => count($this->storage->listSyncDrift($jobId, 'open')),
        ];
    }

    /** @param array<string,mixed> $source @param array<string,mixed> $target */
    public function verify(string $jobId, array $source, array $target): array
    {
        $drift = [];
        foreach (self::DEFAULT_ENTITIES as $entity) {
            $sourceItems = $source[$entity] ?? [];
            $targetItems = $target[$entity] ?? [];
            if (count($sourceItems) !== count($targetItems)) {
                $drift[] = [
                    'entity_type' => $entity,
                    'category' => 'missing_entity',
                    'source_count' => count($sourceItems),
                    'target_count' => count($targetItems),
                ];
            }

            if ($entity === 'files') {
                $sourceChecksum = hash('sha256', json_encode($sourceItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $targetChecksum = hash('sha256', json_encode($targetItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                if ($sourceChecksum !== $targetChecksum) {
                    $drift[] = ['entity_type' => $entity, 'category' => 'file_mismatch', 'source_checksum' => $sourceChecksum, 'target_checksum' => $targetChecksum];
                }
            }
        }

        foreach ($drift as $item) {
            $this->storage->recordSyncDrift(
                'drift_' . bin2hex(random_bytes(6)),
                $jobId,
                (string) $item['entity_type'],
                null,
                null,
                (string) $item['category'],
                'medium',
                $item,
            );
        }

        $conflicts = $this->storage->listSyncConflicts($jobId, 'open');
        $score = max(0.0, min(1.0, 1 - ((count($drift) * 0.03) + (count($conflicts) * 0.05))));
        $this->storage->recordSyncMetric($jobId, [
            'service_state' => 'running',
            'sync_drift_total' => count($drift),
            'sync_conflicts_total' => count($conflicts),
            'sync_health_score' => round($score, 4),
        ]);

        return [
            'job_id' => $jobId,
            'sync_health_score' => round($score, 4),
            'drift_entities' => $drift,
            'conflict_entities' => $conflicts,
        ];
    }

    public function serviceTick(string $jobId): array
    {
        $pending = $this->storage->pendingDeltaQueue($jobId, null, 500);
        $queueBacklog = count($pending);
        $lag = min(600, $queueBacklog * 2);
        $this->storage->recordSyncMetric($jobId, [
            'service_state' => 'running',
            'queue_backlog' => $queueBacklog,
            'replication_lag_seconds' => $lag,
            'sync_operations_total' => count($this->storage->syncLedger($jobId, 1000)),
            'sync_conflicts_total' => count($this->storage->listSyncConflicts($jobId, 'open')),
            'sync_drift_total' => count($this->storage->listSyncDrift($jobId, 'open')),
        ]);

        return [
            'job_id' => $jobId,
            'sync_coordinator' => 'ok',
            'sync_workers' => max(1, min(16, intdiv(max(1, $queueBacklog), 25))),
            'conflict_resolver' => 'active',
            'metrics_collector' => 'active',
            'queue_backlog' => $queueBacklog,
            'replication_lag_seconds' => $lag,
        ];
    }

    public function drStatus(string $jobId): array
    {
        $metrics = $this->storage->latestSyncMetric($jobId);
        $lag = (int) ($metrics['replication_lag_seconds'] ?? 0);
        $pending = (int) ($metrics['queue_backlog'] ?? 0);
        $readiness = max(0.0, min(100.0, 100.0 - ($lag * 0.08) - ($pending * 0.03)));

        return [
            'job_id' => $jobId,
            'replication_lag' => $lag,
            'pending_changes' => $pending,
            'dr_readiness_score' => round($readiness, 2),
        ];
    }

    /** @param array<string,mixed> $config */
    private function enabledEntities(array $config): array
    {
        $policy = $config['sync']['policy'] ?? [];
        $out = [];
        foreach (self::DEFAULT_ENTITIES as $entity) {
            $state = $policy[$entity] ?? 'enabled';
            if ($state !== 'disabled') {
                $out[$entity] = $state;
            }
        }

        return $out;
    }
}
