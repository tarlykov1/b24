<?php

declare(strict_types=1);

namespace MigrationModule\Application\Execution;

use MigrationModule\Application\Control\MigrationControlService;
use MigrationModule\Application\Diff\DiffAnalysisService;
use MigrationModule\Application\Logging\MigrationLogger;
use MigrationModule\Application\Mapping\IdMappingService;
use MigrationModule\Application\Mapping\IntegrityCheckService;
use MigrationModule\Application\Queue\QueueService;
use MigrationModule\Application\Reconciliation\PostMigrationReconciliationService;
use MigrationModule\Application\SelfHealing\HealingAuditLogService;
use MigrationModule\Application\SelfHealing\RepairCycleService;
use MigrationModule\Application\SelfHealing\SelfHealingEngine;
use MigrationModule\Application\State\JobStateService;
use MigrationModule\Application\Throttling\ThrottlingService;
use MigrationModule\Domain\Config\InactiveUserPolicy;
use MigrationModule\Domain\Config\JobSettings;
use MigrationModule\Domain\Config\RunMode;
use MigrationModule\Domain\Log\LogRecord;
use MigrationModule\Domain\SelfHealing\HealingPolicy;

final class MigrationRunner
{
    public function __construct(
        private readonly MigrationControlService $control,
        private readonly QueueService $queue,
        private readonly ThrottlingService $throttling,
        private readonly MigrationLogger $logger,
        private readonly JobStateService $state,
        private readonly IdMappingService $mapping,
        private readonly IntegrityCheckService $integrity,
        private readonly DiffAnalysisService $diff,
        private readonly SelfHealingEngine $healing,
        private readonly HealingAuditLogService $healingAudit,
        private readonly PostMigrationReconciliationService $reconciliation,
        private readonly RepairCycleService $repairCycle,
    ) {
    }

    /**
     * @param array<int,array<string,mixed>> $source
     * @param array<int,array<string,mixed>> $target
     */
    public function run(string $jobId, JobSettings $settings, array $source, array $target = []): array
    {
        if ($settings->mode === RunMode::SYNC) {
            $preview = $this->diff->analyze($jobId, $source, $target);
            $this->logger->log($jobId, new LogRecord('info', 'job', $jobId, null, 'sync_preview_ready', null, 0, 0), 'journal');
            return ['preview' => $preview];
        }

        $this->control->start($jobId);
        $processed = 0;
        $healingPolicy = HealingPolicy::STANDARD;
        $entityIndex = [];

        foreach (array_chunk($source, $settings->batchSize) as $batchNumber => $batch) {
            $batchStart = microtime(true);
            foreach ($batch as $entity) {
                if ($this->isInactiveUserSkipped($entity, $settings)) {
                    $this->logger->log($jobId, new LogRecord('info', (string) $entity['type'], (string) $entity['id'], null, 'entity_skipped_by_cutoff', null, 0, 0), 'journal');
                    continue;
                }

                $entity = $this->healing->healEntity($jobId, $entity, (array) ($entity['errors'] ?? []), $healingPolicy)['entity'];
                $entityIndex[(string) $entity['type'] . ':' . (string) $entity['id']] = $entity;

                $result = $this->mapping->map($jobId, (string) $entity['type'], (string) $entity['id'], static fn (string $desiredId): bool => $desiredId !== 'conflict');
                $this->queue->enqueue($jobId, new \MigrationModule\Domain\Queue\QueueItem((string) $entity['type'], 'upsert', (string) $entity['type'] . ':' . (string) $entity['id'], $entity));
                $this->queue->markDone($jobId, (string) $entity['type'] . ':' . (string) $entity['id']);
                $this->integrity->validateReferences($jobId, (array) ($entity['references'] ?? []));

                $this->state->incrementMetric($jobId, 'processed_entities');
                $this->state->incrementMetric($jobId, 'requests_total', 2);
                if (!$result->preservedId) {
                    $this->logger->log($jobId, new LogRecord('warning', (string) $entity['type'], (string) $entity['id'], (string) $result->targetId, 'id_conflict_remap', $result->remapReason, 0, 0), 'journal');
                }
                ++$processed;
            }

            $this->state->checkpoint($jobId, 'batch', (string) $batchNumber, ['size' => count($batch)]);
            $this->state->incrementMetric($jobId, 'batch_count');
            $this->state->incrementMetric($jobId, 'avg_batch_time_ms', (int) ((microtime(true) - $batchStart) * 1000));
            $this->throttling->pauseBetweenBatches();
        }

        $reconciliation = $this->reconciliation->reconcile($jobId, $this->packByType($source), $this->packByType($target));
        $repair = $this->repairCycle->run($jobId, $reconciliation, $entityIndex, $healingPolicy);

        $this->control->softStop($jobId);
        $this->logger->log($jobId, new LogRecord('info', 'job', $jobId, null, 'job_stopped_after_batches', null, 0, 0), 'journal');

        return [
            'processed' => $processed,
            'metrics' => $this->state->metrics($jobId),
            'healing_audit' => $this->healingAudit->stats($jobId),
            'reconciliation' => $reconciliation,
            'repair_cycle' => $repair,
        ];
    }

    /** @param array<int,array<string,mixed>> $flat @return array<string,array<int,array<string,mixed>>> */
    private function packByType(array $flat): array
    {
        $grouped = [];
        foreach ($flat as $item) {
            $type = (string) ($item['type'] ?? 'unknown');
            $grouped[$type . 's'][] = $item;
        }

        return $grouped;
    }

    /** @param array<string,mixed> $entity */
    private function isInactiveUserSkipped(array $entity, JobSettings $settings): bool
    {
        if (($entity['type'] ?? null) !== 'user' || $settings->inactiveUserCutoffDate === null) {
            return false;
        }

        $lastActive = (string) ($entity['last_active_at'] ?? '');
        if ($lastActive === '' || $lastActive >= $settings->inactiveUserCutoffDate) {
            return false;
        }

        if ($settings->inactiveUserPolicy === InactiveUserPolicy::KEEP_USER) {
            return false;
        }

        if ($settings->inactiveUserPolicy === InactiveUserPolicy::REASSIGN_TO_SYSTEM && $settings->systemAccountId !== null) {
            return true;
        }

        return $settings->inactiveUserPolicy === InactiveUserPolicy::DELETE_TASKS;
    }
}
