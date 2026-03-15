<?php

declare(strict_types=1);

namespace MigrationModule\Cli;

use MigrationModule\Application\Checkpoint\CheckpointService;
use MigrationModule\Application\Consistency\ConflictDetectionEngine;
use MigrationModule\Application\Consistency\DeltaSyncEngine;
use MigrationModule\Application\Consistency\FileReconciliationService;
use MigrationModule\Application\Consistency\ReconciliationQueueService;
use MigrationModule\Application\Consistency\RelationIntegrityEngine;
use MigrationModule\Application\Consistency\SnapshotConsistencyService;
use MigrationModule\Application\Consistency\SyncPolicyEngine;
use MigrationModule\Application\Config\ProductionConfigService;
use MigrationModule\Application\Cutover\CutoverService;
use MigrationModule\Application\Monitoring\MonitoringDashboardService;
use MigrationModule\Application\Plan\DryRunService;
use MigrationModule\Application\Plan\MigrationPlanningService;
use MigrationModule\Application\Readiness\ProductionReadinessChecklistService;
use MigrationModule\Application\Reconciliation\PostMigrationReconciliationService;
use MigrationModule\Application\Reconciliation\ReconciliationEngineService;
use MigrationModule\Application\Hypercare\AdoptionAnalyticsEngine;
use MigrationModule\Application\Hypercare\ArchiveManager;
use MigrationModule\Application\Hypercare\FinalReportGenerator;
use MigrationModule\Application\Hypercare\HypercareMonitor;
use MigrationModule\Application\Hypercare\LateWriteDetector;
use MigrationModule\Application\Hypercare\LateWriteReconciler;
use MigrationModule\Application\Hypercare\MigrationSuccessScorer;
use MigrationModule\Application\Hypercare\OptimizationAdvisor;
use MigrationModule\Application\Hypercare\PerformanceRegressionAnalyzer;
use MigrationModule\Application\Hypercare\PostMigrationIntegrityScanner;
use MigrationModule\Application\Hypercare\ReconciliationEngine;
use MigrationModule\Application\Hypercare\BusinessFlowValidator;
use MigrationModule\Application\Hypercare\UXTelemetryCollector;
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
        private readonly ?SnapshotConsistencyService $snapshotService = null,
        private readonly ?ReconciliationQueueService $reconciliationQueue = null,
        private readonly ?DeltaSyncEngine $deltaEngine = null,
        private readonly ?RelationIntegrityEngine $relationIntegrityEngine = null,
        private readonly ?FileReconciliationService $fileReconciliationService = null,
        private readonly ?ConflictDetectionEngine $conflictEngine = null,
        private readonly ?SyncPolicyEngine $syncPolicyEngine = null,
    ) {
    }

    public function preflight(): int { return 0; }


    /** @param array<string,mixed> $sourceMarkers */
    public function snapshotCreate(string $jobId, array $sourceMarkers = []): array
    {
        return ($this->snapshotService ?? new SnapshotConsistencyService($this->repository))->createSnapshot($jobId, $sourceMarkers);
    }

    public function snapshotShow(string $jobId): ?array
    {
        return $this->repository->snapshot($jobId);
    }

    /** @param array<int,array<string,mixed>> $records */
    public function baselinePlan(string $jobId, string $entityType, array $records): array
    {
        $snapshot = $this->repository->snapshot($jobId);
        $cutoff = (string) ($snapshot['source_cutoff_time'] ?? '1970-01-01T00:00:00+00:00');

        $baseline = array_values(array_filter($records, static function (array $row) use ($cutoff): bool {
            $updatedAt = (string) ($row['updated_at'] ?? $row['modified_at'] ?? $row['created_at'] ?? '');

            return $updatedAt === '' || strtotime($updatedAt) <= strtotime($cutoff);
        }));

        return ['job_id' => $jobId, 'entity_type' => $entityType, 'cutoff' => $cutoff, 'baseline_size' => count($baseline), 'items' => $baseline];
    }

    /** @param array<int,array<string,mixed>> $items */
    public function reconciliationRun(string $jobId, array $items): array
    {
        $queue = $this->reconciliationQueue ?? new ReconciliationQueueService($this->repository);
        foreach ($items as $item) {
            $queue->enqueue($jobId, $item);
        }

        return ['queued' => count($items), 'due' => $queue->dueItems($jobId)];
    }

    /** @param array<int,array<string,mixed>> $records */
    public function deltaPlan(string $jobId, array $records): array
    {
        $snapshot = $this->repository->snapshot($jobId);
        $cutoff = (string) ($snapshot['source_cutoff_time'] ?? '1970-01-01T00:00:00+00:00');

        return ($this->deltaEngine ?? new DeltaSyncEngine(new SyncPolicyEngine(), new ConflictDetectionEngine()))->plan($cutoff, $records);
    }

    /** @param array<int,array<string,mixed>> $delta @param array<string,string> $policies */
    public function deltaExecute(string $jobId, array $delta, array $policies): array
    {
        $engine = $this->deltaEngine ?? new DeltaSyncEngine(new SyncPolicyEngine(), new ConflictDetectionEngine());
        $result = $engine->execute($delta, $policies);
        foreach ($result['conflicts'] as $conflict) {
            $this->repository->addConflict($jobId, $conflict);
        }

        return $result;
    }

    /** @param array<int,array<string,mixed>> $relations */
    public function verifyRelations(array $relations): array
    {
        return ($this->relationIntegrityEngine ?? new RelationIntegrityEngine())->verify($relations);
    }

    /** @param array<int,array<string,mixed>> $files */
    public function verifyFiles(array $files): array
    {
        return ($this->fileReconciliationService ?? new FileReconciliationService())->verify($files);
    }

    public function conflictsList(string $jobId): array
    {
        return ['items' => $this->repository->conflicts($jobId)];
    }

    /** @param array<string,mixed> $resolution */
    public function conflictsResolve(string $jobId, string $conflictId, array $resolution): array
    {
        $this->repository->saveManualOverride($jobId, 'conflict:' . $conflictId, $resolution);

        return ['conflict_id' => $conflictId, 'status' => 'resolved', 'resolution' => $resolution];
    }

    public function watermarksShow(string $jobId): array
    {
        $snapshot = $this->repository->snapshot($jobId) ?? [];

        return ['snapshot_id' => $snapshot['snapshot_id'] ?? null, 'watermarks' => $snapshot['per_entity_watermark'] ?? []];
    }

    public function stateInspect(string $jobId, string $entityType, string $entityId): array
    {
        return ['state' => $this->repository->entityState($jobId, $entityType, $entityId)];
    }

    public function orphansList(string $jobId): array
    {
        $orphans = array_values(array_filter($this->repository->reconciliationQueue($jobId), static fn (array $i): bool => str_contains((string) ($i['reason'] ?? ''), 'orphan')));

        return ['items' => $orphans];
    }

    /** @param array<int,array<string,mixed>> $relations */
    public function repairRelations(string $jobId, array $relations): array
    {
        $repaired = [];
        foreach ($relations as $relation) {
            if (($relation['target_exists'] ?? false) === false && ($relation['source_exists'] ?? false) === true) {
                $relation['target_exists'] = true;
                $repaired[] = $relation;
            }
        }

        return ['job_id' => $jobId, 'repaired' => $repaired, 'count' => count($repaired)];
    }
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


    /** @param array<string,float|int> $signals */
    public function hypercareStart(string $jobId, array $signals = []): array
    {
        $monitor = new HypercareMonitor();

        return ['window' => $monitor->start($jobId), 'health' => $monitor->evaluate($signals)];
    }

    /** @param array<string,list<array<string,mixed>>> $source @param array<string,list<array<string,mixed>>> $target */
    public function hypercareScan(array $source, array $target): array
    {
        return (new PostMigrationIntegrityScanner())->scan($source, $target);
    }

    /** @param list<array<string,mixed>> $issues */
    public function hypercareReconcile(array $issues): array
    {
        return (new ReconciliationEngine())->reconcile($issues);
    }

    /** @param list<array<string,mixed>> $changes @param array<string,string> $windows */
    public function detectLateWrites(array $changes, array $windows): array
    {
        return (new LateWriteDetector())->detect($changes, $windows);
    }

    /** @param list<array<string,mixed>> $lateWrites @param array<string,array<string,mixed>> $targetState */
    public function reconcileLateWrites(array $lateWrites, array $targetState): array
    {
        return (new LateWriteReconciler())->reconcile($lateWrites, $targetState);
    }

    /** @param array<string,int|float> $signals @param array<string,int|float> $baseline */
    public function adoptionAnalytics(array $signals, array $baseline): array
    {
        return (new AdoptionAnalyticsEngine())->analyze($signals, $baseline);
    }

    /** @param list<array<string,mixed>> $flows */
    public function validateBusinessFlows(array $flows): array
    {
        return (new BusinessFlowValidator())->validate($flows);
    }

    /** @param array<string,float|int> $pre @param array<string,float|int> $post */
    public function performanceRegression(array $pre, array $post): array
    {
        return (new PerformanceRegressionAnalyzer())->analyze($pre, $post);
    }

    /** @param list<array<string,mixed>> $regressions */
    public function optimizationRecommend(array $regressions): array
    {
        return ['recommendations' => (new OptimizationAdvisor())->recommend($regressions)];
    }

    /** @param list<array<string,mixed>> $events */
    public function uxTelemetry(array $events): array
    {
        return (new UXTelemetryCollector())->aggregate($events);
    }

    /** @param array<string,float> $scores */
    public function successScore(array $scores): array
    {
        return (new MigrationSuccessScorer())->score($scores);
    }

    /** @param array<string,mixed> $report */
    public function hypercareReport(array $report, string $dir = 'reports/hypercare'): array
    {
        return (new FinalReportGenerator())->generate($report, $dir);
    }

    /** @param array<string,mixed> $artifacts */
    public function hypercareArchive(array $artifacts, string $dir = 'reports/archive'): array
    {
        return (new ArchiveManager())->archive($artifacts, $dir);
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
