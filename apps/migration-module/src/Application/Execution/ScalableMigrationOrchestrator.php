<?php

declare(strict_types=1);

namespace MigrationModule\Application\Execution;

use MigrationModule\Application\Sync\HighWaterMarkSyncService;
use MigrationModule\Application\Throttling\AdaptiveRateLimiter;
use MigrationModule\Infrastructure\Persistence\MigrationRepository;

final class ScalableMigrationOrchestrator
{
    public function __construct(
        private readonly MigrationStagePlanner $planner,
        private readonly AdaptiveRateLimiter $rateLimiter,
        private readonly MigrationRepository $repository,
        private readonly HighWaterMarkSyncService $watermarkSync,
    ) {
    }

    /**
     * @param array<string,array<int,array<string,mixed>>> $source
     * @param array<string,array<int,array<string,mixed>>> $target
     * @return array<string,mixed>
     */
    public function execute(string $jobId, array $source, array $target, bool $initialRun = true, int $chunkSize = 500, int $batchSize = 100): array
    {
        $plan = $this->planner->buildQueuePlan($source, $chunkSize, $batchSize);
        $processed = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($plan as $stageIndex => $stage) {
            foreach ($stage['queues'] as $entityType => $queue) {
                $queueName = sprintf('stage_%d_%s', $stageIndex + 1, $entityType);
                $this->repository->saveQueueState($jobId, $queueName, [
                    'status' => 'running',
                    'chunk_cursor' => 0,
                    'batch_cursor' => 0,
                    'processed' => 0,
                ]);

                foreach ($queue['chunks'] as $chunk) {
                    foreach ($chunk['batches'] as $batch) {
                        usleep($this->rateLimiter->recommendedSleepMs('source') * 1000);
                        $this->rateLimiter->registerSuccess('source');
                        $this->rateLimiter->registerSuccess($entityType === 'files' ? 'heavy' : 'target');

                        $processed += $batch['size'];
                        $this->repository->saveCheckpoint($jobId, [
                            'scope' => 'batch_cursor',
                            'value' => $entityType . ':' . $chunk['chunk_cursor'] . ':' . $batch['batch_cursor'],
                            'meta' => ['stage' => $stage['stage'], 'size' => $batch['size']],
                        ]);

                        $this->repository->saveQueueState($jobId, $queueName, [
                            'status' => 'running',
                            'chunk_cursor' => $chunk['chunk_cursor'],
                            'batch_cursor' => $batch['batch_cursor'],
                            'processed' => $processed,
                        ]);
                    }
                }

                if (!$initialRun) {
                    $delta = $this->watermarkSync->collectDelta(
                        $jobId,
                        $entityType,
                        $source[$entityType] ?? [],
                        $target[$entityType] ?? [],
                    );
                    $updated += $delta['changed'];
                    $skipped += $delta['skipped'];
                }

                $this->repository->saveQueueState($jobId, $queueName, [
                    'status' => 'completed',
                    'chunk_cursor' => null,
                    'batch_cursor' => null,
                    'processed' => $processed,
                ]);
            }
        }

        return [
            'job_id' => $jobId,
            'processed' => $processed,
            'updated' => $updated,
            'skipped' => $skipped,
            'source_rpm' => $this->rateLimiter->currentRpm('source'),
            'target_rpm' => $this->rateLimiter->currentRpm('target'),
            'heavy_rpm' => $this->rateLimiter->currentRpm('heavy'),
            'checkpoints' => $this->repository->latestCheckpoint($jobId),
        ];
    }
}
