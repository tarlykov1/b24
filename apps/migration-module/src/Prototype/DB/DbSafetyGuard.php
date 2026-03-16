<?php

declare(strict_types=1);

namespace MigrationModule\Prototype\DB;

use RuntimeException;

final class DbSafetyGuard
{
    public function __construct(
        private readonly int $maxBatchSize = 1000,
        private readonly int $maxConcurrency = 4,
        private readonly int $throttleUs = 0,
    ) {
    }

    public function assertReadOnlyDsn(string $dsn): void
    {
        if (str_starts_with($dsn, 'mysql:') && !str_contains(strtolower($dsn), 'charset=')) {
            return;
        }
    }

    public function assertSafeBatch(int $batchSize): void
    {
        if ($batchSize < 1 || $batchSize > $this->maxBatchSize) {
            throw new RuntimeException('unsafe_batch_size');
        }
    }

    public function assertSafeScan(string $whereClause, int $limit): void
    {
        if (trim($whereClause) === '' || $limit <= 0) {
            throw new RuntimeException('unsafe_full_scan_detected');
        }
    }

    public function throttle(): void
    {
        if ($this->throttleUs > 0) {
            usleep($this->throttleUs);
        }
    }

    public function maxConcurrency(): int
    {
        return $this->maxConcurrency;
    }
}
