<?php

declare(strict_types=1);

use MigrationModule\Application\Consistency\ConflictDetectionEngine;
use MigrationModule\Application\Consistency\DeltaSyncEngine;
use MigrationModule\Application\Consistency\EntityStateMachine;
use MigrationModule\Application\Consistency\FileReconciliationService;
use MigrationModule\Application\Consistency\ReconciliationQueueService;
use MigrationModule\Application\Consistency\RelationIntegrityEngine;
use MigrationModule\Application\Consistency\SnapshotConsistencyService;
use MigrationModule\Application\Consistency\SyncPolicyEngine;
use MigrationModule\Infrastructure\Persistence\MigrationRepository;
use PHPUnit\Framework\TestCase;

final class SnapshotDeltaReconciliationTest extends TestCase
{
    public function testScenarioAAndFSnapshotBoundaryAndRerunIdempotency(): void
    {
        $repo = new MigrationRepository();
        $jobId = $repo->beginJob('execute');
        $snapshot = (new SnapshotConsistencyService($repo))->createSnapshot($jobId);

        $engine = new DeltaSyncEngine(new SyncPolicyEngine(), new ConflictDetectionEngine());
        $delta = $engine->plan((string) $snapshot['source_cutoff_time'], [
            ['id' => 'd1', 'entity_type' => 'deals', 'updated_at' => date(DATE_ATOM, strtotime('+1 minute'))],
            ['id' => 'd0', 'entity_type' => 'deals', 'updated_at' => date(DATE_ATOM, strtotime('-1 minute'))],
        ]);

        self::assertSame(1, $delta['delta_size']);

        $repo->saveMapping($jobId, 'deals', 'd1', 'td1');
        self::assertSame('td1', $repo->findMappedId($jobId, 'deals', 'd1'));
    }

    public function testScenarioBAndEReconciliationQueueAndStateMachine(): void
    {
        $repo = new MigrationRepository();
        $jobId = $repo->beginJob('execute');
        $queue = new ReconciliationQueueService($repo);
        $queue->enqueue($jobId, ['entity_type' => 'contacts', 'source_id' => 'c1', 'reason' => 'company_relation_pending', 'dependency_type' => 'company']);

        self::assertCount(1, $repo->reconciliationQueue($jobId));

        $machine = new EntityStateMachine();
        self::assertTrue($machine->canTransition('dependency_blocked', 'queued'));
        self::assertTrue($machine->canTransition('reconciled', 'verified'));
    }

    public function testScenarioCFileOrphanRepairSignal(): void
    {
        $files = new FileReconciliationService();
        $report = $files->verify([
            ['id' => 'f1', 'parent_bound' => false, 'source_checksum' => 'a', 'target_checksum' => 'a'],
        ]);

        self::assertSame(1, $report['orphan_file_repair_needed']);
    }

    public function testScenarioDConflictOnManualTargetEdit(): void
    {
        $conflict = (new ConflictDetectionEngine())->detect([
            'mapping_exists' => true,
            'target_exists' => true,
            'source_changed' => true,
            'target_changed' => true,
            'target_changed_manually' => true,
        ]);

        self::assertNotNull($conflict);
        self::assertSame('source_and_target_changed', $conflict['type']);
    }

    public function testScenarioGCountsCanPassButRelationsFail(): void
    {
        $relations = new RelationIntegrityEngine();
        $report = $relations->verify([
            ['source_exists' => true, 'target_exists' => false],
            ['source_exists' => true, 'target_exists' => true],
        ]);

        self::assertFalse($report['healthy']);
        self::assertSame(1, $report['unresolved_relations']);
    }
}
