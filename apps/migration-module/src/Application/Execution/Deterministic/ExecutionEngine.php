<?php

declare(strict_types=1);

namespace MigrationModule\Application\Execution\Deterministic;

use MigrationModule\Prototype\Storage\MySqlStorage;
use Throwable;

final class ExecutionEngine
{
    public function __construct(
        private readonly IdReservationService $idReservation,
        private readonly EntityWriter $writer,
        private readonly ReplayProtectionService $replay,
        private readonly VerificationEngine $verification,
        private readonly MigrationTransactionLog $txLog,
        private readonly FailureClassifier $classifier,
        private readonly RetryPolicy $retryPolicy,
        private readonly MySqlStorage $storage,
    ) {
    }

    public function executeBatch(string $jobId, array $plan, array $batch, array $entitiesByType): array
    {
        $processed = 0;
        $failed = 0;
        foreach ($batch['items'] as $item) {
            $entityType = (string) $item['entity_type'];
            $sourceId = (string) $item['source_id'];
            $entity = $entitiesByType[$entityType][$sourceId] ?? ['id' => $sourceId];
            $payloadHash = sha1(json_encode($entity));
            $key = $this->replay->key((string) $plan['plan_id'], 'write_entities', $entityType, $sourceId, $payloadHash);
            if ($this->replay->alreadySuccessful($key)) {
                continue;
            }

            try {
                $reservation = $this->idReservation->reserve((string) $plan['plan_id'], $plan['target_adapter'], $entityType, $sourceId, (string) ($plan['id_policy'] ?? 'preserve_if_possible'));
                $write = $this->writer->write($entityType, $entity, (string) $reservation['reserved_target_id']);
                $targetId = (string) ($write['target_id'] ?? $reservation['reserved_target_id']);
                $verify = $this->verification->verify($entityType, $targetId, $entity);

$this->storage->saveMapping($jobId, $entityType, $sourceId, $targetId, $payloadHash, 'migrated', ((string) $reservation['reason']) !== 'preserved' ? 1 : 0);

                $this->txLog->step([
                    'step_id' => hash('sha256', implode('|', [$jobId, $plan['plan_id'], 'write_entities', $entityType, $sourceId])),
                    'job_id' => $jobId,
                    'plan_id' => (string) $plan['plan_id'],
                    'phase' => 'write_entities',
                    'batch_id' => (string) $batch['batch_id'],
                    'entity_type' => $entityType,
                    'source_id' => $sourceId,
                    'reserved_target_id' => (string) $reservation['reserved_target_id'],
                    'actual_target_id' => $targetId,
                    'operation_type' => 'upsert',
                    'payload_hash' => $payloadHash,
                    'status' => 'success',
                    'attempt_count' => 1,
                    'verification_status' => (string) $verify['status'],
                    'error_class' => null,
                    'error_code' => null,
                    'diagnostic_blob' => null,
                    'started_at' => date(DATE_ATOM),
                    'finished_at' => date(DATE_ATOM),
                ]);
                $this->replay->remember($key, $jobId, (string) $plan['plan_id'], 'write_entities', $entityType, $sourceId, $payloadHash, 'success');
                $processed++;
            } catch (Throwable $e) {
                $class = $this->classifier->classify($e);
                $retryable = $this->retryPolicy->shouldRetry($class, 0);
                $this->txLog->failure([
                    'job_id' => $jobId,
                    'plan_id' => (string) $plan['plan_id'],
                    'phase' => 'write_entities',
                    'batch_id' => (string) $batch['batch_id'],
                    'entity_type' => $entityType,
                    'source_id' => $sourceId,
                    'classification' => $class,
                    'error_code' => 'write_failed',
                    'diagnostic_blob' => $e->getMessage(),
                    'retryable' => $retryable ? 1 : 0,
                ]);
                $failed++;
            }
        }

        return ['processed' => $processed, 'failed' => $failed, 'batch_id' => $batch['batch_id']];
    }
}
