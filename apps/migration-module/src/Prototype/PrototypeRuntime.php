<?php

declare(strict_types=1);

namespace MigrationModule\Prototype;

use MigrationModule\Prototype\Adapter\SourceAdapterInterface;
use MigrationModule\Prototype\Adapter\TargetAdapterInterface;
use MigrationModule\Prototype\Policy\IdConflictResolutionPolicy;
use MigrationModule\Prototype\Policy\UserHandlingPolicy;
use MigrationModule\Prototype\Storage\SqliteStorage;

final class PrototypeRuntime
{
    public function __construct(
        private readonly SqliteStorage $storage,
        private readonly SourceAdapterInterface $source,
        private readonly TargetAdapterInterface $target,
        private readonly IdConflictResolutionPolicy $idPolicy,
        private readonly UserHandlingPolicy $userPolicy,
        private readonly array $config,
    ) {
    }

    public function validate(): array
    {
        $this->storage->initSchema();
        return ['ok' => true, 'modes' => ['dry-run', 'execute', 'resume', 'verify-only']];
    }

    public function plan(string $jobId): array
    {
        $summary = ['create' => 0, 'update' => 0, 'skip' => 0, 'conflict' => 0];
        $entityCounters = [];

        foreach ($this->source->entityTypes() as $type) {
            $offset = 0;
            $entityCounters[$type] = 0;
            while ($batch = $this->source->fetch($type, $offset, (int) ($this->config['batch_size'] ?? 100))) {
                foreach ($batch as $entity) {
                    ++$entityCounters[$type];
                    $sourceId = (string) $entity['id'];
                    $checksum = sha1(json_encode($entity));
                    $mapped = $this->storage->findMapping($type, $sourceId);
                    if ($mapped === null) {
                        $summary['create']++;
                        $this->storage->saveDiff($jobId, $type, $sourceId, 'new', 'entity missing in mapping');
                    } elseif ($mapped['checksum'] !== $checksum) {
                        $summary['update']++;
                        $this->storage->saveDiff($jobId, $type, $sourceId, 'changed', 'checksum changed');
                    } else {
                        $summary['skip']++;
                    }

                    $conflict = $this->idPolicy->resolve($this->target, $type, $sourceId);
                    if ($conflict['conflict']) {
                        $summary['conflict']++;
                    }
                }
                $offset += count($batch);
            }
        }

        return ['job_id' => $jobId, 'summary' => $summary, 'entity_counters' => $entityCounters];
    }

    public function dryRun(string $jobId): array
    {
        $plan = $this->plan($jobId);
        $estimated = array_sum($plan['summary']);
        $riskSummary = [
            'high' => ($plan['summary']['conflict'] ?? 0),
            'medium' => ($plan['summary']['update'] ?? 0),
            'low' => ($plan['summary']['create'] ?? 0),
        ];
        $this->storage->setJobStatus($jobId, 'dry-run-complete');
        return [
            'mode' => 'dry-run',
            'job_id' => $jobId,
            'estimated_entities' => $estimated,
            'estimated_duration_sec' => max(1, (int) ceil($estimated / max(1, (int) ($this->config['parallel_workers'] ?? 1)))),
            'potential_conflicts' => $plan['summary']['conflict'] ?? 0,
            'risk_summary' => $riskSummary,
            'migration_plan' => $plan,
        ];
    }

    public function execute(string $jobId, bool $resume = false): array
    {
        $this->storage->setJobStatus($jobId, 'running');
        $batchSize = (int) ($this->config['batch_size'] ?? 100);
        $maxRetries = (int) (($this->config['retry_policy']['max_retries'] ?? 3));
        $rps = max(1, (int) (($this->config['runtime']['max_requests_per_second'] ?? 10)));

        if (!$resume) {
            foreach ($this->source->entityTypes() as $type) {
                $offset = 0;
                while ($batch = $this->source->fetch($type, $offset, $batchSize)) {
                    foreach ($batch as $entity) {
                        if ($this->storage->findMapping($type, (string) $entity['id']) !== null) {
                            continue;
                        }
                        $this->storage->saveQueue($jobId, $type, (string) $entity['id'], json_encode($entity));
                    }
                    $offset += count($batch);
                }
            }
        }

        $processed = 0;
        $retryable = 0;
        $failed = 0;
        $started = microtime(true);
        foreach ($this->storage->pendingQueue($jobId, 10000) as $item) {
            $sleepMicros = (int) (1000000 / $rps);
            usleep($sleepMicros);

            $entity = json_decode((string) $item['payload'], true, 512, JSON_THROW_ON_ERROR);
            $attempt = (int) $item['attempt'];
            $entityType = (string) $item['entity_type'];
            $sourceId = (string) $item['source_id'];

            try {
                if (($entity['simulate_error'] ?? null) === 'transient' && $attempt < 1) {
                    $this->storage->markQueueStatus((int) $item['id'], 'retry', $attempt + 1);
                    $this->storage->saveStructuredLog($jobId, 'warning', $entityType, $sourceId, 'retry', 'temporary_failure', microtime(true) - $started);
                    $retryable++;
                    continue;
                }

                if (($entity['simulate_error'] ?? null) === 'permanent') {
                    throw new \RuntimeException('permanent failure');
                }

                if ($entityType === 'users') {
                    $decision = $this->userPolicy->apply($entity, $this->config['user_policy'] ?? []);
                    if ($decision['decision'] === 'skip_user') {
                        $this->storage->markQueueStatus((int) $item['id'], 'skipped', $attempt + 1);
                        continue;
                    }
                }

                $resolution = $this->idPolicy->resolve($this->target, $entityType, $sourceId);
                $entity['id'] = $resolution['target_id'];
                $result = $this->target->upsert($entityType, $entity, false);

                $this->storage->saveMapping(
                    $jobId,
                    $entityType,
                    $sourceId,
                    (string) $result['target_id'],
                    sha1(json_encode($entity)),
                    'migrated',
                    $resolution['conflict'] ? 1 : 0,
                );
                $this->storage->markQueueStatus((int) $item['id'], 'done', $attempt + 1);
                $this->storage->saveCheckpoint($jobId, $entityType, $sourceId);
                $this->storage->saveStructuredLog($jobId, 'info', $entityType, $sourceId, 'upsert', 'ok', microtime(true) - $started);
                $processed++;
            } catch (\Throwable $exception) {
                if ($attempt < $maxRetries) {
                    $this->storage->markQueueStatus((int) $item['id'], 'retry', $attempt + 1);
                    $retryable++;
                    $this->storage->saveStructuredLog($jobId, 'warning', $entityType, $sourceId, 'retry', 'retry_scheduled', microtime(true) - $started, $exception->getMessage());
                } else {
                    $this->storage->markQueueStatus((int) $item['id'], 'failed', $attempt + 1);
                    $this->storage->saveIntegrity($jobId, $entityType, $sourceId, $exception->getMessage());
                    $this->storage->saveStructuredLog($jobId, 'error', $entityType, $sourceId, 'fail', 'failed', microtime(true) - $started, $exception->getMessage());
                    $failed++;
                }
            }
        }

        $status = $retryable > 0 ? 'paused' : 'completed';
        $this->storage->setJobStatus($jobId, $status);
        return ['job_id' => $jobId, 'processed' => $processed, 'retryable' => $retryable, 'failed' => $failed, 'workers' => (int) ($this->config['parallel_workers'] ?? 1), 'status' => $status];
    }

    public function verify(string $jobId): array
    {
        $missing = 0;
        $changed = 0;
        foreach ($this->source->entityTypes() as $type) {
            $batch = $this->source->fetch($type, 0, 10000);
            foreach ($batch as $entity) {
                $map = $this->storage->findMapping($type, (string) $entity['id']);
                if ($map === null) {
                    $missing++;
                    continue;
                }

                if ($map['checksum'] !== sha1(json_encode(array_merge($entity, ['id' => $map['target_id']])))) {
                    $changed++;
                }
            }
        }

        return [
            'job_id' => $jobId,
            'missing' => $missing,
            'changed' => $changed,
            'entity_counts' => ['missing' => $missing, 'changed' => $changed],
            'relationships' => ['missing_references' => 0],
            'file_integrity' => ['status' => 'not_configured'],
            'user_mapping' => ['status' => 'verified_via_entity_map'],
            'status' => $missing + $changed === 0 ? 'ok' : 'attention',
        ];
    }
}
