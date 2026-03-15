<?php

declare(strict_types=1);

use MigrationModule\Application\Consistency\ConsistencyEngine;
use MigrationModule\Application\Consistency\ConflictResolutionEngine;
use MigrationModule\Application\Consistency\RepairEngine;
use MigrationModule\Infrastructure\Persistence\MigrationRepository;
use PHPUnit\Framework\TestCase;

final class MigrationReliabilityEnginesTest extends TestCase
{
    public function testConsistencyEngineRunsAllRequiredChecks(): void
    {
        $engine = new ConsistencyEngine();
        $result = $engine->verifyFull([
            'source_entities' => ['users' => [['id' => '1', 'name' => 'Alice']]],
            'target_entities' => ['users' => [['id' => '1', 'name' => 'Alice']]],
            'relations' => [['id' => 'r1', 'source_exists' => true, 'target_exists' => true]],
            'attachments' => [['id' => 'f1', 'parent_bound' => true, 'source_checksum' => 'x', 'target_checksum' => 'x']],
            'source_custom_fields' => [['code' => 'UF_A', 'type' => 'string']],
            'target_custom_fields' => [['code' => 'UF_A', 'type' => 'string']],
            'source_pipeline_stages' => [['code' => 'NEW']],
            'target_pipeline_stages' => [['code' => 'NEW']],
        ]);

        self::assertArrayHasKey('entity_counts', $result['checks']);
        self::assertArrayHasKey('field_parity', $result['checks']);
        self::assertArrayHasKey('relationships', $result['checks']);
        self::assertArrayHasKey('attachments', $result['checks']);
        self::assertArrayHasKey('custom_fields', $result['checks']);
        self::assertArrayHasKey('pipeline_stages', $result['checks']);
        self::assertTrue($result['healthy']);
    }

    public function testConflictResolutionSupportsPoliciesAndPersistsDecision(): void
    {
        $repo = new MigrationRepository();
        $jobId = $repo->beginJob('verification');
        $engine = new ConflictResolutionEngine($repo);

        $resolved = $engine->resolve($jobId, 'c-1', ['policy' => 'merge', 'notes' => 'prefer non-empty fields']);

        self::assertSame('resolved', $resolved['status']);
        self::assertContains('merge', $engine->supportedPolicies());
        self::assertCount(1, $repo->operatorDecisions($jobId));
        self::assertSame('merge', $repo->operatorDecisions($jobId)[0]['strategy']);
    }

    public function testRepairEngineDetectsAndAppliesPlan(): void
    {
        $repo = new MigrationRepository();
        $jobId = $repo->beginJob('verification');
        $engine = new RepairEngine($repo);

        $plan = $engine->plan($jobId, [
            'relations' => [['id' => 'rel-1', 'entity_type' => 'tasks', 'source_exists' => true, 'target_exists' => false]],
            'users' => [['id' => '42', 'source_exists' => true, 'target_exists' => false]],
            'attachments' => [['id' => 'att-1', 'parent_bound' => false]],
            'pipeline_mismatches' => [['id' => 'deal-10', 'entity_type' => 'crm']],
        ]);

        self::assertSame('detect → repair plan → preview → apply', $plan['workflow']);
        self::assertSame(4, $plan['detected_issues']);

        $applied = $engine->apply($jobId);
        self::assertSame('applied', $applied['status']);
        self::assertSame(4, $applied['applied_total']);
    }
}
