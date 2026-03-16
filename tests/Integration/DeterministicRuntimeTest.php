<?php

declare(strict_types=1);

use MigrationModule\Prototype\Adapter\StubSourceAdapter;
use MigrationModule\Prototype\Adapter\StubTargetAdapter;
use MigrationModule\Prototype\Policy\IdConflictResolutionPolicy;
use MigrationModule\Prototype\Policy\UserHandlingPolicy;
use MigrationModule\Prototype\PrototypeRuntime;
use MigrationModule\Prototype\Storage\SqliteStorage;
use PHPUnit\Framework\TestCase;

final class DeterministicRuntimeTest extends TestCase
{
    public function testExecuteThenResumeIsIdempotent(): void
    {
        $path = sys_get_temp_dir() . '/runtime-deterministic-' . uniqid('', true) . '.sqlite';
        $storage = new SqliteStorage($path);
        $storage->initSchema();
        $jobId = $storage->createJob('execute');

        $runtime = new PrototypeRuntime(
            $storage,
            new StubSourceAdapter(),
            new StubTargetAdapter(),
            new IdConflictResolutionPolicy(),
            new UserHandlingPolicy(),
            ['batch_size' => 10, 'retry_policy' => ['max_retries' => 1], 'runtime' => ['max_requests_per_second' => 100], 'id_preservation_policy' => 'preserve_if_possible', 'storage' => ['path' => $path]],
        );

        $first = $runtime->execute($jobId, false);
        $second = $runtime->execute($jobId, true);

        self::assertSame('completed', $first['status']);
        self::assertSame('completed', $second['status']);
        self::assertSame(0, $second['processed']);
    }
}
