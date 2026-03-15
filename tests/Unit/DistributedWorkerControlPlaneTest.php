<?php

declare(strict_types=1);

use MigrationModule\Application\Execution\DistributedWorkerControlPlane;
use MigrationModule\Application\Throttling\AdaptiveRateLimiter;
use MigrationModule\Prototype\Storage\SqliteStorage;
use PHPUnit\Framework\TestCase;

final class DistributedWorkerControlPlaneTest extends TestCase
{
    public function testInitPauseResumeAndRetryAreResumable(): void
    {
        $storagePath = sys_get_temp_dir() . '/migration-control-plane-' . bin2hex(random_bytes(4)) . '.sqlite';
        $storage = new SqliteStorage($storagePath);
        $storage->initSchema();
        $control = new DistributedWorkerControlPlane($storage, new AdaptiveRateLimiter('safe'));
        $jobId = 'job-control-1';

        $boot = $control->bootstrap($jobId, ['stage_1_users', 'stage_2_tasks'], ['worker-1', 'worker-2']);
        self::assertSame('running', $boot['control_plane']['status']);
        self::assertCount(2, $boot['control_plane']['assignments']);

        $paused = $control->pause($jobId, 'source_load_high');
        self::assertSame('paused', $paused['control_plane']['status']);
        self::assertSame('source_load_high', $paused['control_plane']['paused_reason']);

        $resumed = $control->resume($jobId);
        self::assertSame('running', $resumed['control_plane']['status']);

        $retried = $control->retryQueue($jobId, 'stage_2_tasks');
        self::assertSame(1, $retried['control_plane']['queue_retries']['stage_2_tasks']);
    }


    public function testLeaseLifecycleRecoveryAndDeadLetterQueue(): void
    {
        $storagePath = sys_get_temp_dir() . '/migration-control-plane-' . bin2hex(random_bytes(4)) . '.sqlite';
        $storage = new SqliteStorage($storagePath);
        $storage->initSchema();
        $control = new DistributedWorkerControlPlane($storage, new AdaptiveRateLimiter('balanced'));
        $jobId = 'job-control-3';

        $control->bootstrap($jobId, ['stage_1_contacts'], ['worker-z']);
        $control->enqueueEntity($jobId, 'contact', 'c-10', ['name' => 'A']);
        $control->pauseWorker($jobId, 'worker-z');

        $leaseWhilePaused = $control->leaseNextEntity($jobId, 'worker-z');
        self::assertNull($leaseWhilePaused['leased']);

        $control->resumeWorker($jobId, 'worker-z');
        $lease = $control->leaseNextEntity($jobId, 'worker-z', 1);
        self::assertNotNull($lease['leased']);
        $leaseId = (string) $lease['leased']['lease_id'];

        $control->completeLease($jobId, $leaseId, false, 'transient');
        $lease2 = $control->leaseNextEntity($jobId, 'worker-z', 1);
        $control->completeLease($jobId, (string) $lease2['leased']['lease_id'], false, 'transient');
        $lease3 = $control->leaseNextEntity($jobId, 'worker-z', 1);
        $status = $control->completeLease($jobId, (string) $lease3['leased']['lease_id'], false, 'fatal');

        self::assertSame(1, $status['queue_metrics']['dead_letter_depth']);
    }

    public function testHeartbeatAdjustsAdaptiveThrottlingOnErrors(): void
    {
        $storagePath = sys_get_temp_dir() . '/migration-control-plane-' . bin2hex(random_bytes(4)) . '.sqlite';
        $storage = new SqliteStorage($storagePath);
        $storage->initSchema();
        $limiter = new AdaptiveRateLimiter('balanced');
        $control = new DistributedWorkerControlPlane($storage, $limiter);
        $jobId = 'job-control-2';

        $control->bootstrap($jobId, ['stage_1_users'], ['worker-a']);
        $before = $control->status($jobId);

        $afterFail = $control->heartbeat($jobId, 'worker-a', false, 429);
        self::assertLessThan($before['throttling']['source_rpm'], $afterFail['throttling']['source_rpm']);

        $afterSuccess = $control->heartbeat($jobId, 'worker-a', true);
        self::assertGreaterThan(0, $afterSuccess['throttling']['source_sleep_ms']);
    }
}
