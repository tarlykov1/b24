<?php

declare(strict_types=1);

use MigrationModule\Application\Execution\RetryService;
use MigrationModule\Application\Mapping\IdMappingService;
use MigrationModule\Application\Mapping\ReferenceResolver;
use MigrationModule\Application\Sync\ConflictResolutionService;
use MigrationModule\Application\Sync\CutoffService;
use MigrationModule\Application\Throttling\ThrottlingService;
use MigrationModule\Infrastructure\Persistence\MigrationRepository;
use PHPUnit\Framework\TestCase;

final class CoreServicesTest extends TestCase
{
    public function testIdMapping(): void
    {
        $repository = new MigrationRepository();
        $jobId = $repository->beginJob('initial');
        $service = new IdMappingService($repository);
        $service->map($jobId, 'user', 10, 110);

        self::assertSame('110', $service->resolve($jobId, 'user', 10));
    }

    public function testConflictResolution(): void
    {
        $service = new ConflictResolutionService();
        $resolved = $service->resolve(['name' => 'source'], ['name' => 'target'], 'source_wins');

        self::assertSame('source', $resolved['name']);
    }

    public function testRetryLogic(): void
    {
        $service = new RetryService();
        $attempts = 0;

        $result = $service->run(function () use (&$attempts) {
            $attempts++;
            if ($attempts < 3) {
                throw new RuntimeException('temporary');
            }

            return 'ok';
        }, 3);

        self::assertSame('ok', $result);
        self::assertSame(3, $attempts);
    }

    public function testRateLimiting(): void
    {
        $throttling = new ThrottlingService(2);

        self::assertTrue($throttling->allowRequest());
        self::assertTrue($throttling->allowRequest());
        self::assertFalse($throttling->allowRequest());
    }

    public function testCutoffLogic(): void
    {
        $cutoff = new CutoffService();

        self::assertTrue($cutoff->shouldSync('2025-01-10T11:00:00+00:00', '2025-01-10T10:00:00+00:00'));
        self::assertFalse($cutoff->shouldSync('2025-01-10T09:00:00+00:00', '2025-01-10T10:00:00+00:00'));
    }

    public function testReferenceResolver(): void
    {
        $resolver = new ReferenceResolver();
        $resolved = $resolver->resolve(['responsible_id' => '1'], ['responsible:1' => '101'], 'responsible_id');

        self::assertSame('101', $resolved['responsible_id']);
    }
}
