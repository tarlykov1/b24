<?php

declare(strict_types=1);

use MigrationModule\Application\Cutover\CutoverFinalizationRepository;
use MigrationModule\Application\Cutover\CutoverFinalizationService;
use MigrationModule\Application\Cutover\CutoverFinalizationStateMachine;
use MigrationModule\Application\Cutover\CutoverReadinessEvaluator;
use MigrationModule\Application\Cutover\CutoverVerificationRunner;
use MigrationModule\Application\Cutover\FinalDeltaCollector;
use MigrationModule\Application\Cutover\FreezeWindowManager;
use MigrationModule\Application\Cutover\GoLiveDecisionEngine;
use MigrationModule\Prototype\Storage\MySqlStorage;
use PHPUnit\Framework\TestCase;

final class CutoverFinalizationCoreTest extends TestCase
{
    public function testStateMachineRejectsInvalidTransition(): void
    {
        $sm = new CutoverFinalizationStateMachine();
        $this->expectException(InvalidArgumentException::class);
        $sm->assertTransition('draft', 'final_sync_running');
    }

    public function testReadinessBlockedOnCriticalFailures(): void
    {
        $eval = new CutoverReadinessEvaluator();
        $result = $eval->evaluate(['source_connectivity' => false]);
        self::assertSame('blocked', $result['status']);
        self::assertFalse($result['allow_freeze_activation']);
        self::assertSame('manual_input', $result['checks'][0]['provenance']);
    }

    public function testReadinessClassifiesUnavailableEvidence(): void
    {
        $eval = new CutoverReadinessEvaluator();
        $result = $eval->evaluate([]);
        self::assertSame('blocked', $result['status']);
        self::assertSame('unavailable', $result['checks'][0]['status']);
    }

    public function testStrictFreezeBlocksProtectedMutations(): void
    {
        $freeze = new FreezeWindowManager();
        $out = $freeze->evaluateMutations('strict_freeze', [['entity_type' => 'crm', 'entity_id' => '12']], ['crm']);
        self::assertSame(1, $out['blocking_mutations']);
        self::assertSame('blocked', $out['freeze_policy_result']);
    }

    public function testVerdictRulesReturnOperatorReviewForYellow(): void
    {
        $engine = new GoLiveDecisionEngine();
        $out = $engine->decide([
            'readiness_status' => 'pass_with_warnings',
            'verification_color' => 'yellow',
            'blocking_mutations' => 0,
            'unresolved_critical_errors' => 0,
            'delta_failed_count' => 0,
            'evidence' => [
                'readiness_status' => ['provenance' => 'authoritative'],
                'verification_color' => ['provenance' => 'authoritative'],
            ],
        ]);
        self::assertSame('operator_review_required', $out['verdict']);
        self::assertTrue($out['override_allowed']);
    }

    public function testDuplicatePrepareDoesNotRewindExistingSession(): void
    {
        [$service, $storage] = $this->makeService();
        $service->prepare('freeze-a', 'job-1', 'src', 'tgt', 'ops');
        $service->arm('freeze-a', 'ops', 'advisory_freeze', ['crm']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('duplicate_prepare_conflict:armed');
        $service->prepare('freeze-a', 'job-1', 'src', 'tgt', 'ops');
        unset($storage);
    }

    public function testCompleteFailsWithoutApprovedVerdict(): void
    {
        [$service, $storage] = $this->makeService();
        $freezeId = 'freeze-b';
        $service->prepare($freezeId, 'job-1', 'src', 'tgt', 'ops');
        $service->arm($freezeId, 'ops', 'advisory_freeze', ['crm']);
        $service->freezeStart($freezeId, 'ops', 'advisory_freeze', ['crm'], []);
        $service->finalDelta($freezeId, 'ops', []);
        $service->verify($freezeId, 'ops', [
            'entity_count_diff' => 0,
            'sample_mismatch_count' => 0,
            'mapping_completeness' => 1.0,
            'failed_queue_items' => 0,
            'orphan_references' => 0,
            'missing_attachments' => 0,
            'critical_field_mismatch' => 0,
            'target_write_failures' => 0,
            'missing_required_users' => 0,
            'custom_field_mapping_completeness' => 1.0,
            'target_smoke_ok' => true,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('complete_gate_denied:');
        $service->complete($freezeId, 'ops');
        unset($storage);
    }

    public function testCompleteSucceedsOnlyWithApprovedVerdict(): void
    {
        [$service, $storage] = $this->makeService();
        $freezeId = 'freeze-c';
        $service->prepare($freezeId, 'job-1', 'src', 'tgt', 'ops');
        $service->arm($freezeId, 'ops', 'advisory_freeze', ['crm']);
        $service->freezeStart($freezeId, 'ops', 'advisory_freeze', ['crm'], []);
        $service->finalDelta($freezeId, 'ops', []);
        $service->verify($freezeId, 'ops', [
            'entity_count_diff' => 0,
            'sample_mismatch_count' => 0,
            'mapping_completeness' => 1.0,
            'failed_queue_items' => 0,
            'orphan_references' => 0,
            'missing_attachments' => 0,
            'critical_field_mismatch' => 0,
            'target_write_failures' => 0,
            'missing_required_users' => 0,
            'custom_field_mapping_completeness' => 1.0,
            'target_smoke_ok' => true,
        ]);
        $service->verdict($freezeId, []);

        $completed = $service->complete($freezeId, 'ops');
        self::assertSame('completed', $completed['state']);
        unset($storage);
    }

    public function testResumeOnlyAllowedFromBlockedState(): void
    {
        [$service, $storage] = $this->makeService();
        $freezeId = 'freeze-d';
        $service->prepare($freezeId, 'job-1', 'src', 'tgt', 'ops');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('resume_allowed_only_from_blocked');
        $service->resume($freezeId, 'ops');
        unset($storage);
    }

    /** @return array{0:CutoverFinalizationService,1:MySqlStorage} */
    private function makeService(): array
    {
        $path = sys_get_temp_dir() . '/cutover-finalization-core-' . bin2hex(random_bytes(4)) . '.sqlite';
        $storage = new MySqlStorage($path);
        $storage->initSchema();

        return [
            new CutoverFinalizationService(
                new CutoverFinalizationRepository($storage->pdo()),
                new CutoverFinalizationStateMachine(),
                new CutoverReadinessEvaluator(),
                new FreezeWindowManager(),
                new FinalDeltaCollector($storage),
                new CutoverVerificationRunner(),
                new GoLiveDecisionEngine(),
                $storage,
            ),
            $storage,
        ];
    }
}
