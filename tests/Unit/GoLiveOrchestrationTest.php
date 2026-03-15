<?php

declare(strict_types=1);

use MigrationModule\Application\GoLive\CutoverRehearsalEngine;
use MigrationModule\Application\GoLive\FinalDeltaSyncOrchestrator;
use MigrationModule\Application\GoLive\FreezePolicyManager;
use MigrationModule\Application\GoLive\GoLiveReadinessEngine;
use MigrationModule\Application\GoLive\GoLiveStateMachine;
use MigrationModule\Application\GoLive\PreflightCheckRunner;
use MigrationModule\Application\GoLive\RollbackCoordinator;
use MigrationModule\Application\GoLive\SmokeTestRunner;
use PHPUnit\Framework\TestCase;

final class GoLiveOrchestrationTest extends TestCase
{
    public function testReadinessScoringAndBlockers(): void
    {
        $engine = new GoLiveReadinessEngine();
        $result = $engine->assess([
            'completedMigrationWaves' => 2,
            'requiredMigrationWaves' => 3,
            'remainingQueueSize' => 700,
            'unresolvedIntegrityIssues' => 4,
            'unresolvedMappingConflicts' => 2,
            'workerHealth' => 0.6,
            'retryStormDetected' => true,
            'lastDryRunOk' => false,
            'lastVerificationOk' => true,
            'openManualDecisions' => true,
            'pendingApprovals' => true,
        ]);

        self::assertLessThan(60, $result['readinessScore']);
        self::assertContains('retry_storm_detected', $result['hardBlockers']);
        self::assertContains('last_dry_run_failed', $result['mustFixBeforeCutover']);
        self::assertSame('not_ready', $result['recommendation']);
    }

    public function testStateMachineTransitions(): void
    {
        $sm = new GoLiveStateMachine();
        $transition = $sm->transition('freeze-active', 'delta-sync-running', ['timeoutSec' => 900]);
        self::assertSame('delta-sync-running', $transition['to']);

        $this->expectException(RuntimeException::class);
        $sm->transition('draft', 'completed');
    }

    public function testPreflightFailAndOverride(): void
    {
        $runner = new PreflightCheckRunner();
        $fail = $runner->evaluate([
            ['name' => 'source', 'severity' => 'critical', 'result' => false, 'message' => 'down'],
        ]);
        self::assertSame('FAIL', $fail['status']);

        $override = $runner->evaluate([
            ['name' => 'source', 'severity' => 'critical', 'result' => false, 'message' => 'down'],
        ], 'approved emergency', 'ops-1');
        self::assertSame('PASS_WITH_WARNINGS', $override['status']);
        self::assertNotNull($override['override']);
    }

    public function testFreezePolicyBehaviorOperationalWhenNoLock(): void
    {
        $manager = new FreezePolicyManager();
        $freeze = $manager->activate(['freezeType' => 'partial', 'domains' => ['crm'], 'allowlist' => ['vip-order']], ['technical_lock' => false], 'ops-1');

        self::assertSame('operational_freeze', $freeze['mode']);
        self::assertSame(['crm'], $freeze['lockedDomains']);
    }

    public function testDeltaSyncOrchestrationPriorityAndEta(): void
    {
        $orch = new FinalDeltaSyncOrchestrator();
        $result = $orch->run('actual_final_delta_execution', [
            'sourceQpsLimit' => 50,
            'workerCount' => 10,
            'changes' => [
                ['entityFamily' => 'files', 'count' => 100, 'updated' => 90],
                ['entityFamily' => 'users', 'count' => 50, 'updated' => 50],
            ],
        ]);

        self::assertSame('users', $result['buckets'][0]['entityFamily']);
        self::assertGreaterThan(0, $result['etaMin']);
        self::assertGreaterThanOrEqual(25, $result['safeBatchSize']);
    }

    public function testSmokeEvaluationRules(): void
    {
        $runner = new SmokeTestRunner();
        $result = $runner->evaluate([
            ['name' => 'login', 'severity' => 'critical', 'pass' => true],
            ['name' => 'crm', 'severity' => 'major', 'pass' => false],
        ]);

        self::assertSame('stabilize_or_partial_rollback', $result['decision']);
    }

    public function testRollbackDecisionModel(): void
    {
        $coordinator = new RollbackCoordinator();
        $result = $coordinator->decide([
            'switchCompleted' => true,
            'criticalSmokeFailed' => true,
            'domainFailures' => 0,
            'targetWritesAfterSwitch' => 500,
        ]);

        self::assertSame('rollback_technically_possible', $result['rollbackPossibility']);
        self::assertSame('full_rollback_to_source_primary', $result['recommendedScenario']);
    }

    public function testRehearsalPredictionSanity(): void
    {
        $engine = new CutoverRehearsalEngine();
        $result = $engine->simulate(['entityVolume' => 20000, 'avgWorkerThroughput' => 100, 'workers' => 8, 'errorRate' => 0.03]);

        self::assertGreaterThan(0, $result['predictedDurationMin']);
        self::assertGreaterThan(0, $result['confidenceScore']);
    }
}
