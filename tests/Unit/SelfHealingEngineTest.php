<?php

declare(strict_types=1);

use MigrationModule\Application\SelfHealing\ErrorTaxonomy;
use MigrationModule\Application\SelfHealing\HealingAuditLogService;
use MigrationModule\Application\SelfHealing\RepairCycleService;
use MigrationModule\Application\SelfHealing\SafeDataSanitizer;
use MigrationModule\Application\SelfHealing\SelfHealingEngine;
use MigrationModule\Domain\SelfHealing\HealingPolicy;
use MigrationModule\Infrastructure\Persistence\MigrationRepository;
use PHPUnit\Framework\TestCase;

final class SelfHealingEngineTest extends TestCase
{
    public function testTimeoutAndRateLimitAreRetried(): void
    {
        $repo = new MigrationRepository();
        $jobId = $repo->beginJob('initial_import');
        $taxonomy = new ErrorTaxonomy();
        $audit = new HealingAuditLogService($repo);
        $engine = new SelfHealingEngine($taxonomy, new SafeDataSanitizer(), $audit, $repo);

        $entity = ['id' => '1', 'type' => 'task', 'title' => '  Hello  '];
        $errors = [
            ['message' => 'API timeout on create'],
            ['message' => '429 rate limit'],
        ];

        $result = $engine->healEntity($jobId, $entity, $errors, HealingPolicy::STANDARD);

        self::assertSame('Hello', $result['entity']['title']);
        self::assertSame(0, $result['quarantine_queue_size']);
        self::assertGreaterThanOrEqual(2, count($repo->healingAuditLog($jobId)));
    }

    public function testMissingStageConservativeGoesToQuarantine(): void
    {
        $repo = new MigrationRepository();
        $jobId = $repo->beginJob('initial_import');
        $taxonomy = new ErrorTaxonomy();
        $audit = new HealingAuditLogService($repo);
        $engine = new SelfHealingEngine($taxonomy, new SafeDataSanitizer(), $audit, $repo);

        $entity = ['id' => '50', 'type' => 'crm_deal'];
        $errors = [['message' => 'missing stage in target', 'missing' => 'stage']];

        $result = $engine->healEntity($jobId, $entity, $errors, HealingPolicy::CONSERVATIVE);

        self::assertSame(1, $result['quarantine_queue_size']);
        self::assertCount(1, $repo->quarantineItems($jobId));
    }

    public function testMissingEnumStandardAutoCreatesMetadata(): void
    {
        $repo = new MigrationRepository();
        $jobId = $repo->beginJob('initial_import');
        $engine = new SelfHealingEngine(new ErrorTaxonomy(), new SafeDataSanitizer(), new HealingAuditLogService($repo), $repo);

        $entity = ['id' => '52', 'type' => 'crm_deal'];
        $result = $engine->healEntity($jobId, $entity, [['message' => 'missing enum value', 'missing' => 'UF_STATUS']], HealingPolicy::STANDARD);

        self::assertSame(['UF_STATUS'], $result['entity']['metadata_repairs']);
    }

    public function testMissingUserRemapsToFallback(): void
    {
        $repo = new MigrationRepository();
        $jobId = $repo->beginJob('initial_import');
        $engine = new SelfHealingEngine(new ErrorTaxonomy(), new SafeDataSanitizer(), new HealingAuditLogService($repo), $repo);

        $result = $engine->healEntity($jobId, ['id' => '11', 'type' => 'task', 'responsible_id' => '404'], [[
            'message' => 'missing user',
            'fallback_user_id' => '1',
        ]]);

        self::assertSame('1', $result['entity']['responsible_id']);
    }

    public function testDuplicateAmbiguousGoesToQuarantine(): void
    {
        $repo = new MigrationRepository();
        $jobId = $repo->beginJob('initial_import');
        $engine = new SelfHealingEngine(new ErrorTaxonomy(), new SafeDataSanitizer(), new HealingAuditLogService($repo), $repo);

        $result = $engine->healEntity($jobId, ['id' => '11', 'type' => 'contact'], [[
            'message' => 'duplicate by phone',
            'match_type' => 'ambiguous',
        ]]);

        self::assertSame(1, $result['quarantine_queue_size']);
    }

    public function testInvalidPayloadAndBrokenAttachmentAreHealed(): void
    {
        $repo = new MigrationRepository();
        $jobId = $repo->beginJob('initial_import');
        $engine = new SelfHealingEngine(new ErrorTaxonomy(), new SafeDataSanitizer(), new HealingAuditLogService($repo), $repo);

        $result = $engine->healEntity($jobId, ['id' => '91', 'type' => 'file', 'name' => '  bad\x00name  '], [
            ['message' => 'validation error malformed payload'],
            ['message' => 'file transfer error on upload'],
        ]);

        self::assertSame('badname', $result['entity']['name']);
        self::assertTrue($result['entity']['file_healing']['checksum_verified']);
    }

    public function testLostMappingLowConfidenceIsQuarantined(): void
    {
        $repo = new MigrationRepository();
        $jobId = $repo->beginJob('initial_import');
        $engine = new SelfHealingEngine(new ErrorTaxonomy(), new SafeDataSanitizer(), new HealingAuditLogService($repo), $repo);

        $result = $engine->healEntity($jobId, ['id' => '10', 'type' => 'deal'], [[
            'message' => 'mapping mismatch after restart',
            'confidence' => 30,
        ]]);

        self::assertSame(1, $result['quarantine_queue_size']);
    }

    public function testRestartAfterCrashTriggersDeadLetterOnExceededAttempts(): void
    {
        $repo = new MigrationRepository();
        $jobId = $repo->beginJob('initial_import');
        $engine = new SelfHealingEngine(new ErrorTaxonomy(), new SafeDataSanitizer(), new HealingAuditLogService($repo), $repo);

        for ($i = 0; $i < 5; $i++) {
            $engine->healEntity($jobId, ['id' => '10', 'type' => 'deal'], [[
                'message' => 'missing stage',
            ]], HealingPolicy::CONSERVATIVE);
        }

        self::assertNotEmpty($repo->deadLetterItems($jobId));
    }

    public function testRepairCycleCreatesJobsFromReconciliationMismatch(): void
    {
        $repo = new MigrationRepository();
        $jobId = $repo->beginJob('verification');
        $taxonomy = new ErrorTaxonomy();
        $repair = new RepairCycleService(
            new SelfHealingEngine($taxonomy, new SafeDataSanitizer(), new HealingAuditLogService($repo), $repo),
            $taxonomy,
        );

        $reconciliation = [
            'groups' => ['tasks' => ['mismatched' => 1, 'missing_in_target' => 1]],
            'unresolved_links' => [['entity' => 'tasks', 'id' => '10', 'relation' => 'responsible_id']],
        ];
        $outcome = $repair->run($jobId, $reconciliation, ['tasks:10' => ['id' => '10', 'type' => 'tasks']]);

        self::assertCount(1, $outcome['repair_jobs']);
        self::assertCount(1, $outcome['residual_issues']);
    }
}
