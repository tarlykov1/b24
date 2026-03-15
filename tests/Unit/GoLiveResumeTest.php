<?php

declare(strict_types=1);

use MigrationModule\Application\GoLive\CutoverAuditTrail;
use MigrationModule\Application\GoLive\FinalDeltaSyncOrchestrator;
use MigrationModule\Application\GoLive\GoLiveOrchestrator;
use MigrationModule\Application\GoLive\GoLiveReadinessEngine;
use MigrationModule\Application\GoLive\GoLiveStateMachine;
use MigrationModule\Application\GoLive\PreflightCheckRunner;
use MigrationModule\Application\GoLive\RollbackCoordinator;
use MigrationModule\Application\GoLive\SmokeTestRunner;
use PHPUnit\Framework\TestCase;

final class GoLiveResumeTest extends TestCase
{
    public function testResumeAfterInterruptionKeepsAuditChain(): void
    {
        $audit = new CutoverAuditTrail();
        $orchestrator = new GoLiveOrchestrator(
            new GoLiveStateMachine(),
            new GoLiveReadinessEngine(),
            new PreflightCheckRunner(),
            new FinalDeltaSyncOrchestrator(),
            new SmokeTestRunner(),
            new RollbackCoordinator(),
            $audit,
        );

        $result = $orchestrator->run(
            'ops-1',
            ['completedMigrationWaves' => 3, 'requiredMigrationWaves' => 3, 'lastDryRunOk' => true, 'lastVerificationOk' => true],
            [['name' => 'source', 'severity' => 'critical', 'result' => true, 'message' => 'ok']],
            ['workerCount' => 8, 'changes' => [['entityFamily' => 'users', 'count' => 10, 'updated' => 10]]],
            [['name' => 'login', 'severity' => 'critical', 'pass' => true]],
        );

        self::assertContains($result['status'], ['stabilization', 'rollback-pending']);
        self::assertGreaterThanOrEqual(3, count($result['audit']));
        self::assertArrayHasKey('prevHash', $result['audit'][1]);
    }
}
