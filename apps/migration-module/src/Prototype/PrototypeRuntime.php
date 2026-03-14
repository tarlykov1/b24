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

        foreach ($this->source->entityTypes() as $type) {
            $offset = 0;
            while ($batch = $this->source->fetch($type, $offset, (int) ($this->config['batch_size'] ?? 100))) {
                foreach ($batch as $entity) {
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

        return ['job_id' => $jobId, 'summary' => $summary];
    }

    public function dryRun(string $jobId): array
    {
        $plan = $this->plan($jobId);
        $this->storage->setJobStatus($jobId, 'dry-run-complete');
        return ['mode' => 'dry-run', 'job_id' => $jobId] + $plan;
    }

    public function execute(string $jobId, bool $resume = false): array
    {
        $this->storage->setJobStatus($jobId, 'running');
        if (!$resume) {
            foreach ($this->source->entityTypes() as $type) {
                $offset = 0;
                while ($batch = $this->source->fetch($type, $offset, (int) ($this->config['batch_size'] ?? 100))) {
                    foreach ($batch as $entity) {
                        $this->storage->saveQueue($jobId, $type, (string) $entity['id'], json_encode($entity));
                    }
                    $offset += count($batch);
                }
            }
        }

        $processed = 0;
        $retryable = 0;
        foreach ($this->storage->pendingQueue($jobId, 10000) as $item) {
            $entity = json_decode((string) $item['payload'], true, 512, JSON_THROW_ON_ERROR);
            $attempt = (int) $item['attempt'];

            if (($entity['simulate_error'] ?? null) === 'transient' && $attempt < 1) {
                $this->storage->markQueueStatus((int) $item['id'], 'retry', $attempt + 1);
                $this->storage->saveLog($jobId, 'warning', 'transient error, requeued ' . $item['source_id']);
                $retryable++;
                continue;
            }

            if (($entity['simulate_error'] ?? null) === 'permanent') {
                $this->storage->markQueueStatus((int) $item['id'], 'failed', $attempt + 1);
                $this->storage->saveIntegrity($jobId, (string) $item['entity_type'], (string) $item['source_id'], 'permanent failure');
                continue;
            }

            if ($item['entity_type'] === 'users') {
                $decision = $this->userPolicy->apply($entity, $this->config['user_policy'] ?? []);
                if ($decision['decision'] === 'skip_user') {
                    $this->storage->markQueueStatus((int) $item['id'], 'skipped', $attempt + 1);
                    continue;
                }
            }

            $resolution = $this->idPolicy->resolve($this->target, (string) $item['entity_type'], (string) $item['source_id']);
            $entity['id'] = $resolution['target_id'];
            $result = $this->target->upsert((string) $item['entity_type'], $entity, false);

            $this->storage->saveMapping(
                $jobId,
                (string) $item['entity_type'],
                (string) $item['source_id'],
                (string) $result['target_id'],
                sha1(json_encode($entity)),
                'migrated',
                $resolution['conflict'] ? 1 : 0,
            );
            $this->storage->markQueueStatus((int) $item['id'], 'done', $attempt + 1);
            $this->storage->saveCheckpoint($jobId, (string) $item['entity_type'], (string) $item['source_id']);
            $processed++;
        }

        $status = $retryable > 0 ? 'paused' : 'completed';
        $this->storage->setJobStatus($jobId, $status);
        return ['job_id' => $jobId, 'processed' => $processed, 'retryable' => $retryable, 'status' => $status];
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

        return ['job_id' => $jobId, 'missing' => $missing, 'changed' => $changed, 'status' => $missing + $changed === 0 ? 'ok' : 'attention'];
    }
}
