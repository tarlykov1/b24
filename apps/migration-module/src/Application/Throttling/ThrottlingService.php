<?php

declare(strict_types=1);

namespace MigrationModule\Application\Throttling;

final class ThrottlingService
{
    /** @return array{batch_size:int, workers:int, rpm:int, file_parallelism:int, sleep_ms:int} */
    public function currentLimits(): array
    {
        return [
            'batch_size' => 100,
            'workers' => 1,
            'rpm' => 60,
            'file_parallelism' => 1,
            'sleep_ms' => 500,
        ];
    }

    public function registerErrorSignal(): void
    {
        // TODO: adaptive slowdown policy.
    }
}
