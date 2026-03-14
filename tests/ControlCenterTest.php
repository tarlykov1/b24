<?php

declare(strict_types=1);

use MigrationModule\ControlCenter\Controller\JobController;
use MigrationModule\ControlCenter\Service\ConflictResolver;
use MigrationModule\ControlCenter\Service\DiffEngine;
use MigrationModule\ControlCenter\Service\IntegrityRepairService;
use MigrationModule\ControlCenter\Service\MigrationMonitor;
use MigrationModule\Prototype\Storage\SqliteStorage;
use PHPUnit\Framework\TestCase;

final class ControlCenterTest extends TestCase
{
    private string $dbPath;
    private SqliteStorage $storage;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/control-center-' . bin2hex(random_bytes(4)) . '.sqlite';
        $this->storage = new SqliteStorage($this->dbPath);
        $this->storage->initSchema();
    }

    protected function tearDown(): void
    {
        @unlink($this->dbPath);
    }

    public function testDiffGenerationDetectsChangedAndMismatch(): void
    {
        $engine = new DiffEngine();
        $diff = $engine->compare('crm_deals', 4811, ['TITLE' => 'Deal A', 'STAGE_ID' => 'NEW'], ['TITLE' => 'Deal A (copy)', 'STAGE_ID' => 'IN_PROGRESS']);

        self::assertSame('changed', $diff['diff'][0]['status']);
        self::assertSame('mismatch', $diff['diff'][1]['status']);
    }

    public function testConflictDetectionAndResolution(): void
    {
        $jobId = $this->storage->createJob('execute');
        $this->storage->saveIntegrity($jobId, 'task', '8842', 'missing user 55');

        $resolver = new ConflictResolver($this->storage->pdo());
        $conflicts = $resolver->list($jobId);
        $result = $resolver->resolve($jobId, $conflicts[0], 'assign_system_user');

        self::assertSame('user_missing', $conflicts[0]['type']);
        self::assertSame('resolved', $result['status']);
    }

    public function testRepairExecutionRequiresConfirmation(): void
    {
        $jobId = $this->storage->createJob('verify');
        $this->storage->saveIntegrity($jobId, 'crm_deal', '5122', 'missing_relation');

        $service = new IntegrityRepairService($this->storage->pdo());
        $issues = $service->issues($jobId);

        $pending = $service->repairIssue($jobId, $issues[0], false);
        $done = $service->repairIssue($jobId, $issues[0], true);

        self::assertSame('confirmation_required', $pending['status']);
        self::assertSame('repaired', $done['status']);
    }

    public function testDashboardMetricsExposeProgress(): void
    {
        $jobId = $this->storage->createJob('execution');
        $this->storage->saveQueue($jobId, 'users', '1', '{}', 'done');
        $this->storage->saveQueue($jobId, 'users', '2', '{}', 'failed');
        $this->storage->saveQueue($jobId, 'users', '3', '{}', 'retry');

        $monitor = new MigrationMonitor($this->storage->pdo());
        $dashboard = $monitor->dashboard($jobId);

        self::assertSame(3, $dashboard['metrics']['total_entities']);
        self::assertSame(1, $dashboard['metrics']['processed_entities']);
        self::assertSame(1, $dashboard['errors']);
    }

    public function testOperatorCommandsPauseResumeRetry(): void
    {
        $jobId = $this->storage->createJob('execution');
        $this->storage->saveQueue($jobId, 'tasks', '10', '{}', 'failed');

        $controller = new JobController($this->storage->pdo());
        $paused = $controller->pause($jobId);
        $resumed = $controller->resume($jobId);
        $retry = $controller->retryEntity($jobId, 'tasks', '10');

        self::assertSame('paused', $paused['status']);
        self::assertSame('execution', $resumed['status']);
        self::assertSame('retry_queued', $retry['status']);
    }
}
