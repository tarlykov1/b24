<?php

declare(strict_types=1);

namespace MigrationModule\Application\Execution\Deterministic;

use MigrationModule\Prototype\Storage\SqliteStorage;

final class StateStore
{
    public function __construct(private readonly SqliteStorage $storage)
    {
    }

    public function savePlan(string $jobId, array $plan): void
    {
        $this->storage->saveMigrationPlan($jobId, (string) $plan['plan_id'], (string) $plan['plan_hash'], sha1(json_encode($plan['scope'])), $plan);
    }

    public function saveBatches(string $jobId, string $planId, array $batches): void
    {
        foreach ($batches as $batch) {
            $this->storage->saveExecutionBatch($jobId, $planId, (string) $batch['batch_id'], (string) $batch['phase'], (string) $batch['entity_type'], (int) $batch['stable_order'], 'pending');
        }
    }
}
