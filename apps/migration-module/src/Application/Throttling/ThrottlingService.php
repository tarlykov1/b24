<?php

declare(strict_types=1);

namespace MigrationModule\Application\Throttling;

final class ThrottlingService
{
    /** @var array{batch_size:int,source_rpm:int,target_rpm:int,pause_between_batches_ms:int,max_retries:int,backoff_base_ms:int} */
    private array $limits;

    public function __construct(?array $limits = null)
    {
        $this->limits = $limits ?? [
            'batch_size' => 50,
            'source_rpm' => 30,
            'target_rpm' => 30,
            'pause_between_batches_ms' => 750,
            'max_retries' => 5,
            'backoff_base_ms' => 250,
        ];
    }

    /** @return array{batch_size:int,source_rpm:int,target_rpm:int,pause_between_batches_ms:int,max_retries:int,backoff_base_ms:int} */
    public function currentLimits(): array
    {
        return $this->limits;
    }

    public function pauseBetweenBatches(): void
    {
        usleep($this->limits['pause_between_batches_ms'] * 1000);
    }

    public function backoffDelayMs(int $attempt): int
    {
        return $this->limits['backoff_base_ms'] * (2 ** max(0, $attempt - 1));
    }
}
