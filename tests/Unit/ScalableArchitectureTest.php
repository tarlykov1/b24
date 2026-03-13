<?php

declare(strict_types=1);

use MigrationModule\Application\Execution\MigrationStagePlanner;
use MigrationModule\Application\Execution\ScalableMigrationOrchestrator;
use MigrationModule\Application\Sync\HighWaterMarkSyncService;
use MigrationModule\Application\Throttling\AdaptiveRateLimiter;
use MigrationModule\Infrastructure\Persistence\MigrationRepository;
use PHPUnit\Framework\TestCase;

final class ScalableArchitectureTest extends TestCase
{
    public function testStagePlannerBuildsDependencyAwareQueues(): void
    {
        $planner = new MigrationStagePlanner();
        $plan = $planner->buildQueuePlan([
            'users' => [['id' => '1'], ['id' => '2']],
            'deals' => [['id' => '10']],
            'files' => [['id' => 'f-1']],
        ], 1, 1);

        self::assertCount(3, $plan);
        self::assertSame('stage_1_reference_bootstrap', $plan[0]['stage']);
        self::assertTrue($plan[0]['queues']['users']['parallel_safe']);
        self::assertFalse($plan[2]['queues']['files']['parallel_safe']);
    }

    public function testAdaptiveRateLimiterDecreasesAndRecovers(): void
    {
        $limiter = new AdaptiveRateLimiter('balanced');
        $initial = $limiter->currentRpm('source');

        $limiter->registerFailure('source', 429);
        self::assertLessThan($initial, $limiter->currentRpm('source'));

        $limiter->registerSuccess('source');
        self::assertGreaterThan(0, $limiter->recommendedSleepMs('source'));
    }

    public function testHighWatermarkDetectsDeltaAndPersistsCheckpoint(): void
    {
        $repo = new MigrationRepository();
        $jobId = $repo->beginJob('incremental_sync');
        $service = new HighWaterMarkSyncService($repo);

        $first = $service->collectDelta($jobId, 'contacts', [
            ['id' => '1', 'name' => 'A', 'updated_at' => '2025-01-01T10:00:00+00:00'],
        ], []);
        self::assertSame(1, $first['new']);

        $second = $service->collectDelta($jobId, 'contacts', [
            ['id' => '1', 'name' => 'A+', 'updated_at' => '2025-01-01T11:00:00+00:00'],
        ], [
            ['id' => '1', 'name' => 'A', 'updated_at' => '2025-01-01T10:00:00+00:00'],
        ]);
        self::assertSame(1, $second['changed']);
        self::assertNotNull($repo->latestCheckpoint($jobId));
    }

    public function testOrchestratorStoresQueueStateForResume(): void
    {
        $repo = new MigrationRepository();
        $jobId = $repo->beginJob('initial_import');
        $orchestrator = new ScalableMigrationOrchestrator(
            new MigrationStagePlanner(),
            new AdaptiveRateLimiter('safe'),
            $repo,
            new HighWaterMarkSyncService($repo),
        );

        $result = $orchestrator->execute($jobId, ['users' => [['id' => '1']]], ['users' => []], true, 1, 1);

        self::assertSame(1, $result['processed']);
        self::assertSame('completed', $repo->queueState($jobId, 'stage_1_users')['status']);
    }
}
