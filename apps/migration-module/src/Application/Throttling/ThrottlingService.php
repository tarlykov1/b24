<?php

declare(strict_types=1);

namespace MigrationModule\Application\Throttling;

final class ThrottlingService
{
    private int $requestsInWindow = 0;
    private int $windowStartedAt;
    private int $rpm;

    public function __construct(int $rpm = 60)
    {
        $this->rpm = $rpm;
        $this->windowStartedAt = time();
    }

    /** @return array{batch_size:int, workers:int, rpm:int, file_parallelism:int, sleep_ms:int} */
    public function currentLimits(): array
    {
        return [
            'batch_size' => 100,
            'workers' => 1,
            'rpm' => $this->rpm,
            'file_parallelism' => 1,
            'sleep_ms' => 500,
        ];
    }

    public function allowRequest(): bool
    {
        if ((time() - $this->windowStartedAt) >= 60) {
            $this->windowStartedAt = time();
            $this->requestsInWindow = 0;
        }

        if ($this->requestsInWindow >= $this->rpm) {
            return false;
        }

        $this->requestsInWindow++;

        return true;
    }

    public function registerErrorSignal(): void
    {
        $this->rpm = max(10, $this->rpm - 5);
    }
}
