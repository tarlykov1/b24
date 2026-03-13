<?php

declare(strict_types=1);

namespace MigrationModule\Application\Throttling;

final class ThrottlingService
{
    private int $rpm = 60;
    private int $lastRequestAt = 0;

    public function currentRpm(): int
    {
        return $this->rpm;
    }

    public function throttle(): void
    {
        $intervalMs = (int) floor(60000 / max(1, $this->rpm));
        $elapsedMs = (int) floor((microtime(true) * 1000) - $this->lastRequestAt);
        if ($this->lastRequestAt > 0 && $elapsedMs < $intervalMs) {
            usleep(($intervalMs - $elapsedMs) * 1000);
        }

        $this->lastRequestAt = (int) floor(microtime(true) * 1000);
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
