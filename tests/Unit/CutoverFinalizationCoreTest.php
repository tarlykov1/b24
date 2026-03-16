<?php

declare(strict_types=1);

use MigrationModule\Application\Cutover\CutoverFinalizationStateMachine;
use MigrationModule\Application\Cutover\CutoverReadinessEvaluator;
use MigrationModule\Application\Cutover\FreezeWindowManager;
use MigrationModule\Application\Cutover\GoLiveDecisionEngine;
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
        ]);
        self::assertSame('operator_review_required', $out['verdict']);
        self::assertTrue($out['override_allowed']);
    }
}
