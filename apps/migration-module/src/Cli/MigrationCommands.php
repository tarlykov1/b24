<?php

declare(strict_types=1);

namespace MigrationModule\Cli;

use MigrationModule\Application\Plan\DryRunService;
use MigrationModule\Application\Plan\MigrationPlanningService;
use MigrationModule\Application\Reconciliation\PostMigrationReconciliationService;
use MigrationModule\Application\Report\FinalReportService;
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

    /** @param array<string,mixed> $payload */
    public function exportReports(array $payload, string $dir = 'reports'): array
    {
        return $this->reportService->writeBundle($payload, $dir);
    }
}
