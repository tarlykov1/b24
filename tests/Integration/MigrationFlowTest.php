<?php

declare(strict_types=1);

use MigrationModule\Application\Runtime\MigrationRuntimeService;
use MigrationModule\Application\Verification\VerificationService;
use MigrationModule\Infrastructure\Persistence\MigrationRepository;
use PHPUnit\Framework\TestCase;

final class MigrationFlowTest extends TestCase
{
    public function testUserAndTaskMigrationAndRelationsValidation(): void
    {
        $repository = new MigrationRepository();
        $jobId = $repository->beginJob('initial');

        $source = [
            'users' => [['id' => '1', 'email' => 'a@x.io', 'active' => true]],
            'tasks' => [['id' => '10', 'responsible_id' => '1', 'created_by' => '1', 'deadline' => '2025-01-11', 'status' => 'new']],
            'comments' => [['id' => '100', 'author' => '1', 'task_id' => '10', 'created_at' => '2025-01-11T10:00:00+00:00']],
            'crm_contacts' => [], 'crm_companies' => [], 'crm_deals' => [], 'files' => [], 'custom_fields' => [],
        ];

        $repository->setSourceSnapshot($jobId, $source);
        $repository->setTargetSnapshot($jobId, $source);
        $repository->saveMapping($jobId, 'user', '1', '1');
        $repository->saveMapping($jobId, 'task', '10', '10');
        $repository->saveMapping($jobId, 'comment', '100', '100');

        $report = (new VerificationService($repository))->verify($jobId);
        self::assertSame('pass', $report['integrity_check']['result']);
        self::assertSame('pass', $report['statistics_comparison']['status']);
    }

    public function testPauseResumeViaCheckpointAndCrashRecovery(): void
    {
        $repository = new MigrationRepository();
        $jobId = $repository->beginJob('initial');
        $runtime = new MigrationRuntimeService($repository);
        $entities = [
            ['id' => '1'], ['id' => '2'], ['id' => '3'], ['id' => '4'],
        ];

        try {
            $runtime->migrateWithCheckpoint($jobId, 'user', $entities, 2, 3);
            self::fail('Expected crash.');
        } catch (RuntimeException) {
            self::assertNotNull($repository->latestCheckpoint($jobId));
        }

        $processedAfterRestart = $runtime->migrateWithCheckpoint($jobId, 'user', $entities, 2);
        self::assertGreaterThan(0, $processedAfterRestart);
        self::assertSame('4', $repository->findMappedId($jobId, 'user', '4'));
    }
}
