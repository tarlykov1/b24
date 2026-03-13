<?php

declare(strict_types=1);

use MigrationModule\Application\Checkpoint\CheckpointService;
use MigrationModule\Application\Cutover\CutoverService;
use MigrationModule\Application\Freeze\FreezeModeService;
use MigrationModule\Application\Hardening\ResilientApiExecutor;
use MigrationModule\Application\Rollback\RollbackService;
use MigrationModule\Application\Sync\DeltaSyncService;
use MigrationModule\Infrastructure\Persistence\MigrationRepository;
use PHPUnit\Framework\TestCase;

final class ProductionStageTest extends TestCase
{
    public function testCutoverTest(): void
    {
        $repo = new MigrationRepository();
        $jobId = $repo->beginJob('cutover');
        $service = new CutoverService($repo, new DeltaSyncService($repo), new FreezeModeService());

        $result = $service->execute(
            $jobId,
            'users',
            ['main_transfer_done' => true, 'critical_errors' => 0],
            [['id' => '1', 'updated_at' => '2025-01-01T10:00:00+00:00']],
            [],
            true,
            true,
            ['freeze_supported' => false],
        );

        self::assertSame('COMPLETED', $result['migration_status']);
        self::assertFalse($result['freeze']['enabled']);
    }

    public function testRollbackTest(): void
    {
        $repo = new MigrationRepository();
        $jobId = $repo->beginJob('rollback');
        $repo->saveMapping($jobId, 'users', '1', '101');
        $repo->saveMapping($jobId, 'files', '2', '202');

        $result = (new RollbackService($repo))->run($jobId, 'critical_error', 'cutover', 'full', 'reports/rollback_report.test.json');

        self::assertSame(1, $result['entities_deleted']);
        self::assertSame(1, $result['entities_unable_to_delete']);
    }

    public function testFreezeModeTest(): void
    {
        $service = new FreezeModeService();
        $result = $service->activate(['freeze_supported' => true]);

        self::assertTrue($result['enabled']);
        self::assertContains('disable_automations', $result['actions']);
    }

    public function testCheckpointRecoveryTest(): void
    {
        $path = 'migration_state.test.json';
        @unlink($path);

        $service = new CheckpointService($path, 1, 1);
        $service->advance('delta_sync', 'users:55', ['pending' => 10], 1);
        $loaded = $service->load();

        self::assertSame('delta_sync', $loaded['stage']);
        self::assertSame('users:55', $loaded['last_entity']);

        @unlink($path);
    }

    public function testApiFailureSimulation(): void
    {
        $executor = new ResilientApiExecutor(maxRetries: 2, baseDelayMs: 1);

        $this->expectException(RuntimeException::class);
        $executor->execute(static fn (): array => ['status' => 500]);
    }

    public function testRateLimitHandling(): void
    {
        $calls = 0;
        $executor = new ResilientApiExecutor(maxRetries: 2, baseDelayMs: 1);
        $result = $executor->execute(static function () use (&$calls): array {
            $calls++;

            return $calls === 1 ? ['status' => 429] : ['status' => 200, 'data' => ['ok' => true]];
        });

        self::assertSame(2, $calls);
        self::assertSame(200, $result['status']);
        self::assertGreaterThan(0.0, $executor->metrics()['retry_rate']);
    }
}
