<?php

declare(strict_types=1);

namespace MigrationModule\Cli;

use MigrationModule\Application\Checkpoint\CheckpointService;
use MigrationModule\Application\Config\ProductionConfigService;
use MigrationModule\Application\Cutover\CutoverService;
use MigrationModule\Application\Monitoring\MonitoringDashboardService;
use MigrationModule\Application\Plan\DryRunService;
use MigrationModule\Application\Plan\MigrationPlanningService;
use MigrationModule\Application\Readiness\ProductionReadinessChecklistService;
use MigrationModule\Application\Reconciliation\PostMigrationReconciliationService;
use MigrationModule\Application\Reconciliation\ReconciliationEngineService;
use MigrationModule\Application\Report\CertificationReportService;
use MigrationModule\Application\Report\FinalReportService;
use MigrationModule\Application\Rollback\RollbackService;
use MigrationModule\Application\Sync\DeltaSyncService;
use MigrationModule\Infrastructure\Persistence\MigrationRepository;

final class MigrationCommands
{
    public function __construct(
        private readonly MigrationRepository $repository,
        private readonly DryRunService $dryRunService,
        private readonly MigrationPlanningService $planningService,
        private readonly PostMigrationReconciliationService $reconciliationService,
        private readonly DeltaSyncService $deltaSyncService,
        private readonly FinalReportService $reportService,
        private readonly CutoverService $cutoverService,
        private readonly RollbackService $rollbackService,
        private readonly CheckpointService $checkpointService,
        private readonly ProductionConfigService $configService,
        private readonly MonitoringDashboardService $dashboardService,
        private readonly ProductionReadinessChecklistService $readinessChecklist,
    ) {
    }

    public function preflight(): int { return 0; }

    public function audit(): int { return 0; }

    public function createJob(string $mode = 'initial'): string
    {
        return $this->repository->beginJob($mode);
    }

    /** @param array<string, array<int, array<string, mixed>>> $source @param array<string, array<int, array<string, mixed>>> $target */
    public function dryRun(string $jobId, array $source, array $target, bool $incremental = false): array
    {
        return $this->dryRunService->execute($jobId, $source, $target, $incremental);
    }

    /** @param array<string, array<int, array<string, mixed>>> $source @param array<string, array<int, array<string, mixed>>> $target */
    public function migrationPlan(string $jobId, array $source, array $target, bool $incremental = false): array
    {
        return $this->planningService->buildPlan($jobId, $source, $target, $incremental);
    }

    /** @param array<string, array<int, array<string, mixed>>> $source @param array<string, array<int, array<string, mixed>>> $target */
    public function reconcile(string $jobId, array $source, array $target): array
    {
        return $this->reconciliationService->reconcile($jobId, $source, $target);
    }

    /** @param array<int,array<string,mixed>> $sourceRecords @param array<int,array<string,mixed>> $targetRecords */
    public function deltaSyncPreview(string $jobId, string $entityType, array $sourceRecords, array $targetRecords): array
    {
        return $this->deltaSyncService->detectDelta(
            $jobId,
            $entityType,
            $sourceRecords,
            $targetRecords,
            $this->repository->syncCheckpoint($entityType),
        );
    }

    /** @param array<string,mixed> $verificationReport @param array<int,array<string,mixed>> $sourceRecords @param array<int,array<string,mixed>> $targetRecords */
    public function cutover(
        string $jobId,
        string $entityType,
        array $verificationReport,
        array $sourceRecords,
        array $targetRecords,
        bool $confirmStart,
        bool $confirmSwitch,
    ): array {
        return $this->cutoverService->execute(
            $jobId,
            $entityType,
            $verificationReport,
            $sourceRecords,
            $targetRecords,
            $confirmStart,
            $confirmSwitch,
            ['freeze_supported' => true],
        );
    }

    public function rollback(string $jobId, string $reason, string $stage, string $mode = 'safe'): array
    {
        return $this->rollbackService->run($jobId, $reason, $stage, $mode);
    }

    /** @param array<string,mixed> $queueState */
    public function checkpoint(string $stage, ?string $lastEntity, array $queueState): ?array
    {
        $this->checkpointService->save($stage, $lastEntity, $queueState);

        return $this->checkpointService->load();
    }

    public function status(string $jobId): array
    {
        return [
            'job_id' => $jobId,
            'stage' => $this->repository->jobStatus($jobId) ?? 'unknown',
            'progress' => count($this->repository->mappings($jobId)),
            'stats' => [
                'reports' => count($this->repository->reports($jobId)),
                'decisions' => count($this->repository->operatorDecisions($jobId)),
            ],
        ];
    }

    /** @param array<string,mixed> $status @param array<string,float|int> $metrics */
    public function dashboard(array $status, array $metrics): array
    {
        return $this->dashboardService->build($status, $metrics);
    }

    /** @param array<string,bool> $checks */
    public function readiness(array $checks): array
    {
        return $this->readinessChecklist->evaluate($checks);
    }

    /** @return array<string,mixed> */
    public function config(string $path = 'migration.config.yml'): array
    {
        return $this->configService->load($path);
    }

    /** @param array<string,mixed> $payload */
    public function exportReports(array $payload, string $dir = 'reports'): array
    {
        return $this->reportService->writeBundle($payload, $dir);
    }

    /** @param array<string, array<int, array<string, mixed>>> $source @param array<string, array<int, array<string, mixed>>> $target @param array<string,mixed> $context */
    public function verifyAndCertify(string $jobId, array $source, array $target, array $context = [], string $dir = 'reports'): array
    {
        $engine = new ReconciliationEngineService($this->repository);
        $certifier = new CertificationReportService();

        $reconciliation = $engine->run($jobId, $source, $target, [
            'batch_size' => 500,
            'rate_limit_rps' => 20,
            'async_workers' => 4,
        ]);

        $certification = $certifier->generate($context, $reconciliation, $dir);

        return [
            'reconciliation' => $reconciliation,
            'certification' => $certification,
        ];
    }
}
