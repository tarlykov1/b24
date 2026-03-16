<?php

declare(strict_types=1);

use MigrationModule\Application\Cutover\CutoverRepository;
use MigrationModule\Application\Cutover\CutoverService;
use MigrationModule\Application\Cutover\CutoverStateMachine;
use MigrationModule\Prototype\Storage\SqliteStorage;
use PHPUnit\Framework\TestCase;

final class CutoverOrchestratorTest extends TestCase
{
    public function testPlanReadinessApproveGoLiveAndReport(): void
    {
        $dbPath = sys_get_temp_dir() . '/cutover-test-' . bin2hex(random_bytes(3)) . '.sqlite';
        $storage = new SqliteStorage($dbPath);
        $storage->initSchema();
        $jobId = $storage->createJob('execute');

        $service = new CutoverService(new CutoverRepository($storage->pdo()), new CutoverStateMachine());
        $cutoverId = 'cutover_unit_1';

        $plan = $service->plan($jobId, $cutoverId, [
            'cutover_window' => ['start' => date(DATE_ATOM, time() - 60), 'end' => date(DATE_ATOM, time() + 300), 'timezone' => 'UTC', 'allow_finish_after_window_end' => true],
        ], 'tester');
        self::assertSame('planned', $plan['status']);

        $readiness = $service->readiness($cutoverId, ['completed_waves' => 3, 'required_waves' => 3, 'residual_delta' => 0]);
        self::assertSame('ready_for_go_live', $readiness['status']);

        $service->approve($cutoverId, 'migration_operator', 'op-1');
        $service->approve($cutoverId, 'technical_owner', 'cto-1');
        $approval = $service->approve($cutoverId, 'business_owner', 'biz-1');
        self::assertSame('approved', $approval['status']);

        $goLive = $service->goLive($cutoverId, true, false);
        self::assertSame('live', $goLive['status']);

        $report = $service->report($cutoverId);
        self::assertSame('live', $report['final_decision']);
        self::assertNotEmpty($report['switch_journal']);
    }
}
