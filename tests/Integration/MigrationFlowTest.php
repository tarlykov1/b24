<?php

declare(strict_types=1);

use MigrationModule\Application\Plan\DryRunService;
use MigrationModule\Application\Plan\MigrationPlanningService;
use MigrationModule\Application\Reconciliation\PostMigrationReconciliationService;
use MigrationModule\Application\Sync\DeltaSyncService;
use MigrationModule\Infrastructure\Persistence\MigrationRepository;
use PHPUnit\Framework\TestCase;

final class MigrationFlowTest extends TestCase
{
    public function testFirstRunThenRerunWithoutChanges(): void
    {
        $repo = new MigrationRepository();
        $jobId = $repo->beginJob('initial_import');
        $planner = new MigrationPlanningService($repo);

        $source = ['users' => [['id' => '1', 'email' => 'a@x.io']]];
        $target = ['users' => []];
        $first = $planner->buildPlan($jobId, $source, $target);
        self::assertSame(1, $first['summary']['create']);

        $repo->saveMapping($jobId, 'users', '1', '1');
        $second = $planner->buildPlan($jobId, $source, ['users' => [['id' => '1', 'email' => 'a@x.io']]], true);
        self::assertSame(1, $second['summary']['skip']);
    }

    public function testRerunWithNewAndChangedData(): void
    {
        $repo = new MigrationRepository();
        $jobId = $repo->beginJob('delta_sync');
        $repo->saveSyncCheckpoint('users', '2025-01-01T09:00:00+00:00');
        $delta = new DeltaSyncService($repo);

        $source = [
            ['id' => '1', 'email' => 'new@x.io', 'updated_at' => '2025-01-01T10:00:00+00:00'],
            ['id' => '2', 'email' => 'fresh@x.io', 'updated_at' => '2025-01-01T10:00:00+00:00'],
        ];
        $target = [['id' => '1', 'email' => 'old@x.io', 'updated_at' => '2025-01-01T08:00:00+00:00']];

        $result = $delta->detectDelta($jobId, 'users', $source, $target, $repo->syncCheckpoint('users'));
        self::assertSame(1, $result['new']);
        self::assertSame(1, $result['changed']);
    }

    public function testInactiveUserCutoffSideEffectOnTasksAndIdConflictInTarget(): void
    {
        $repo = new MigrationRepository();
        $jobId = $repo->beginJob('dry_run');
        $dryRun = new DryRunService(new MigrationPlanningService($repo));

        $source = [
            'users' => [['id' => '99', 'email' => 'inactive@x.io', 'requires_manual_review' => true]],
            'tasks' => [['id' => '500', 'created_by' => '99', 'responsible_id' => '99']],
        ];
        $target = ['users' => [['id' => '99', 'email' => 'active@x.io']], 'tasks' => [['id' => '500', 'created_by' => '1']]];

        $preview = $dryRun->execute($jobId, $source, $target);
        self::assertSame(1, $preview['summary']['manual_review']);
        self::assertSame(1, $preview['summary']['conflicts']);
    }

    public function testVerificationPauseResumeCheckpointSimulation(): void
    {
        $repo = new MigrationRepository();
        $jobId = $repo->beginJob('verification');
        $repo->saveCheckpoint($jobId, ['scope' => 'verification', 'value' => 'users:50%', 'meta' => ['paused' => true]]);

        $checkpoint = $repo->latestCheckpoint($jobId);
        self::assertSame('verification', $checkpoint['scope']);

        $service = new PostMigrationReconciliationService($repo);
        $report = $service->reconcile($jobId, ['users' => [['id' => '1']]], ['users' => [['id' => '1']]]);
        self::assertSame(1, $report['groups']['users']['matched']);
    }
}
