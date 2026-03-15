<?php

declare(strict_types=1);

use MigrationModule\Application\Consistency\ConsistencyEngine;
use MigrationModule\Domain\Job\JobLifecycle;
use MigrationModule\Infrastructure\Persistence\MigrationRepository;
use PHPUnit\Framework\TestCase;

final class RuntimeLifecycleSmokeTest extends TestCase
{
    public function testLifecycleTransitionValidation(): void
    {
        $repo = new MigrationRepository();
        $jobId = $repo->beginJob('execute');

        self::assertSame(JobLifecycle::CREATED, $repo->jobStatus($jobId));
        $repo->setJobStatus($jobId, JobLifecycle::PLANNED);
        $repo->setJobStatus($jobId, JobLifecycle::RUNNING);
        $repo->setJobStatus($jobId, JobLifecycle::COMPLETED);
        $repo->setJobStatus($jobId, JobLifecycle::VERIFIED);

        self::assertSame(JobLifecycle::VERIFIED, $repo->jobStatus($jobId));
    }

    public function testInvalidTransitionIsBlockedWithMachineReadableError(): void
    {
        $repo = new MigrationRepository();
        $jobId = $repo->beginJob('execute');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('invalid_job_transition');
        $repo->setJobStatus($jobId, JobLifecycle::COMPLETED);
    }

    public function testMissingJobIsBlockedWithMachineReadableError(): void
    {
        $repo = new MigrationRepository();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('job_not_found');
        $repo->reports('job-missing');
    }

    public function testVerifyDepthIsExplicitForShallowAndFullChecks(): void
    {
        $engine = new ConsistencyEngine();

        $shallow = $engine->verifyCounts(['users' => [['id' => '1']]], ['users' => [['id' => '1']]]);
        $full = $engine->verifyFull([
            'source_entities' => ['users' => [['id' => '1']]],
            'target_entities' => ['users' => [['id' => '1']]],
            'relations' => [],
            'attachments' => [],
            'source_custom_fields' => [],
            'target_custom_fields' => [],
            'source_pipeline_stages' => [],
            'target_pipeline_stages' => [],
        ]);

        self::assertSame('structural', $shallow['depth']);
        self::assertSame('full', $full['verify_depth']);
        self::assertContains('source_to_target_adapter_live_content_diff', $full['not_checked']);
    }
}
