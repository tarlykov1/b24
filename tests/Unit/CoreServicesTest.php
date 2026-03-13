<?php

declare(strict_types=1);

use MigrationModule\Application\Plan\DryRunService;
use MigrationModule\Application\Plan\MigrationPlanningService;
use MigrationModule\Application\Reconciliation\PostMigrationReconciliationService;
use MigrationModule\Application\Sync\ConflictResolutionService;
use MigrationModule\Application\Sync\DeltaSyncService;
use MigrationModule\Infrastructure\Persistence\MigrationRepository;
use PHPUnit\Framework\TestCase;

final class CoreServicesTest extends TestCase
{
    public function testDryRunBuildsPlanWithoutWrites(): void
    {
        $repo = new MigrationRepository();
        $jobId = $repo->beginJob('dry_run');
        $service = new DryRunService(new MigrationPlanningService($repo));

        $source = ['users' => [['id' => '1', 'email' => 'a@x.io']], 'tasks' => [['id' => '10', 'responsible_id' => '1']]];
        $target = ['users' => [], 'tasks' => []];
        $result = $service->execute($jobId, $source, $target);

        self::assertSame('dry_run', $result['mode']);
        self::assertSame(0, $result['write_operations']);
        self::assertSame(2, $result['summary']['to_create']);
    }

    public function testMigrationPlanDetectsConflictAndManualReview(): void
    {
        $repo = new MigrationRepository();
        $jobId = $repo->beginJob('initial_import');
        $planner = new MigrationPlanningService($repo);

        $source = ['users' => [['id' => '1', 'email' => 'old@x.io'], ['id' => '2', 'requires_manual_review' => true]]];
        $target = ['users' => [['id' => '1', 'email' => 'new@x.io']]];

        $plan = $planner->buildPlan($jobId, $source, $target);

        self::assertSame(1, $plan['summary']['conflict']);
        self::assertSame(1, $plan['summary']['manual_review']);
    }

    public function testDeltaSyncDetectsNewChangedAndConflict(): void
    {
        $repo = new MigrationRepository();
        $jobId = $repo->beginJob('delta_sync');
        $repo->saveSyncCheckpoint('tasks', '2025-01-01T09:00:00+00:00');
        $service = new DeltaSyncService($repo);

        $source = [
            ['id' => '1', 'title' => 'New', 'updated_at' => '2025-01-01T10:00:00+00:00'],
            ['id' => '2', 'title' => 'Changed', 'updated_at' => '2025-01-01T10:00:00+00:00'],
        ];
        $target = [
            ['id' => '2', 'title' => 'Old', 'updated_at' => '2025-01-01T11:00:00+00:00'],
        ];

        $delta = $service->detectDelta($jobId, 'tasks', $source, $target, $repo->syncCheckpoint('tasks'));

        self::assertSame(1, $delta['new']);
        self::assertSame(1, $delta['changed']);
        self::assertSame(1, $delta['conflicts']);
    }

    public function testConflictDecisionIsStored(): void
    {
        $repo = new MigrationRepository();
        $jobId = $repo->beginJob('initial_import');
        $service = new ConflictResolutionService($repo);

        $service->saveDecision($jobId, ['type' => 'id_already_taken', 'entity' => 'tasks', 'source_id' => '10'], 'create_with_new_id');

        self::assertCount(1, $repo->operatorDecisions($jobId));
        self::assertSame('create_with_new_id', $repo->operatorDecisions($jobId)[0]['strategy']);
    }

    public function testReconciliationTotalsAndUnresolvedLinks(): void
    {
        $repo = new MigrationRepository();
        $jobId = $repo->beginJob('verification');
        $service = new PostMigrationReconciliationService($repo);

        $source = [
            'users' => [['id' => '1', 'email' => 'a@x.io']],
            'crm_deals' => [['id' => '50', 'company_id' => '500']],
            'tasks' => [['id' => '10', 'created_by' => '77', 'responsible_id' => '1']],
            'comments' => [['id' => '100', 'task_id' => '10']],
        ];
        $target = ['users' => [['id' => '1', 'email' => 'a@x.io']], 'crm_deals' => [], 'crm_companies' => [], 'tasks' => [], 'comments' => []];

        $report = $service->reconcile($jobId, $source, $target);

        self::assertSame(1, $report['groups']['users']['matched']);
        self::assertGreaterThan(0, count($report['unresolved_links']));
    }
}
