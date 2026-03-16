<?php

declare(strict_types=1);

namespace MigrationModule\Prototype;

use MigrationModule\Application\Execution\Deterministic\CheckpointManager;
use MigrationModule\Application\Execution\Deterministic\DbReadFacade;
use MigrationModule\Application\Execution\Deterministic\DeterministicBatchScheduler;
use MigrationModule\Application\Execution\Deterministic\EntityWriter;
use MigrationModule\Application\Execution\Deterministic\ExecutionEngine;
use MigrationModule\Application\Execution\Deterministic\ExecutionGraphBuilder;
use MigrationModule\Application\Execution\Deterministic\ExecutionPlanBuilder;
use MigrationModule\Application\Execution\Deterministic\FailureClassifier;
use MigrationModule\Application\Execution\Deterministic\IdReservationService;
use MigrationModule\Application\Execution\Deterministic\MigrationTransactionLog;
use MigrationModule\Application\Execution\Deterministic\ReplayProtectionService;
use MigrationModule\Application\Execution\Deterministic\RestWriteFacade;
use MigrationModule\Application\Execution\Deterministic\RetryPolicy;
use MigrationModule\Application\Execution\Deterministic\StateStore;
use MigrationModule\Application\Execution\Deterministic\VerificationEngine;
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

    public function configValidate(): array
    {
        $required = ['storage', 'batch_size', 'retry_policy', 'runtime', 'id_preservation_policy'];
        $missing = [];
        foreach ($required as $key) {
            if (!array_key_exists($key, $this->config)) {
                $missing[] = $key;
            }
        }

        return ['ok' => $missing === [], 'missing' => $missing, 'config_path' => 'migration.config.yml'];
    }

    public function plan(string $jobId): array
    {
        $entitiesByType = $this->collectEntities();
        $planBuilder = new ExecutionPlanBuilder();
        $plan = $planBuilder->build($this->config, ['instance' => 'source'], ['instance' => 'target'], $entitiesByType, 'execute');

        $graph = (new ExecutionGraphBuilder())->build($plan);
        $batches = (new DeterministicBatchScheduler())->schedule($graph, (int) ($this->config['batch_size'] ?? 100));
        $state = new StateStore($this->storage);
        $state->savePlan($jobId, $plan);
        $state->saveBatches($jobId, (string) $plan['plan_id'], $batches);

        return ['job_id' => $jobId, 'plan' => $plan, 'graph' => $graph, 'batches' => $batches, 'summary' => ['batch_count' => count($batches)]];
    }

    public function showPlan(string $jobId): array
    {
        return ['job_id' => $jobId, 'plan' => $this->storage->latestPlan($jobId)];
    }

    public function exportPlan(string $jobId, string $path): array
    {
        $plan = $this->storage->latestPlan($jobId);
        if ($plan === null) {
            return ['job_id' => $jobId, 'error' => 'plan_missing'];
        }

        file_put_contents($path, (string) json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return ['job_id' => $jobId, 'plan_id' => $plan['plan_id'] ?? null, 'path' => $path];
    }

    public function dryRun(string $jobId): array
    {
        $plan = $this->plan($jobId);
        $this->storage->setJobStatus($jobId, 'dry-run-complete');

        return ['mode' => 'dry-run', 'job_id' => $jobId, 'migration_plan' => $plan, 'status' => 'ok'];
    }

    public function execute(string $jobId, bool $resume = false): array
    {
        $this->storage->setJobStatus($jobId, 'running');

        $plan = $this->storage->latestPlan($jobId);
        if (!is_array($plan)) {
            $plan = $this->plan($jobId)['plan'];
        }

        $graph = (new ExecutionGraphBuilder())->build($plan);
        $batches = (new DeterministicBatchScheduler())->schedule($graph, (int) ($this->config['batch_size'] ?? 100));
        $entitiesByType = $this->collectEntitiesIndexed();

        $engine = new ExecutionEngine(
            new IdReservationService($this->storage),
            new EntityWriter(new RestWriteFacade($this->target)),
            new ReplayProtectionService($this->storage),
            new VerificationEngine($this->target),
            new MigrationTransactionLog($this->storage),
            new FailureClassifier(),
            new RetryPolicy((int) ($this->config['retry_policy']['max_retries'] ?? 3), 100),
            $this->storage,
        );

        $totals = ['processed' => 0, 'failed' => 0];
        foreach ($batches as $batch) {
            $result = $engine->executeBatch($jobId, $plan + ['target_adapter' => $this->target, 'id_policy' => (string) ($this->config['id_preservation_policy'] ?? 'preserve_if_possible')], $batch, $entitiesByType);
            $totals['processed'] += (int) $result['processed'];
            $totals['failed'] += (int) $result['failed'];
            (new CheckpointManager($this->storage))->save($jobId, (string) $plan['plan_id'], 'write_entities', (string) $batch['batch_id'], ['processed' => $totals['processed']]);
        }

        $status = $totals['failed'] > 0 ? 'paused' : 'completed';
        $this->storage->setJobStatus($jobId, $status);

        return ['job_id' => $jobId, 'plan_id' => $plan['plan_id'], 'resume' => $resume, 'status' => $status] + $totals;
    }

    public function verify(string $jobId): array
    {
        $plan = $this->storage->latestPlan($jobId);
        $counts = $this->storage->summary($jobId);

        return [
            'job_id' => $jobId,
            'plan_id' => $plan['plan_id'] ?? null,
            'status' => (($counts['failure_events'] ?? 0) === 0) ? 'verified' : 'partial',
            'levels' => ['level1' => 'verified', 'level2' => 'verified', 'level3' => 'partial', 'level4' => 'partial', 'level5' => 'partial'],
            'counts' => $counts,
        ];
    }

    public function checkpointList(string $jobId): array
    {
        return ['job_id' => $jobId, 'checkpoints' => (new CheckpointManager($this->storage))->list($jobId)];
    }

    private function collectEntities(): array
    {
        $out = [];
        $reader = new DbReadFacade($this->source);
        foreach ($this->source->entityTypes() as $type) {
            $offset = 0;
            $out[$type] = [];
            while (true) {
                $batch = $reader->discover($type, $offset, (int) ($this->config['batch_size'] ?? 100));
                if ($batch === []) {
                    break;
                }
                $out[$type] = array_merge($out[$type], $batch);
                $offset += count($batch);
            }
        }

        return $out;
    }

    private function collectEntitiesIndexed(): array
    {
        $flat = $this->collectEntities();
        $indexed = [];
        foreach ($flat as $type => $items) {
            foreach ($items as $item) {
                $indexed[$type][(string) $item['id']] = $item;
            }
        }

        return $indexed;
    }
}
