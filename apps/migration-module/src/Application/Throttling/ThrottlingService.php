<?php

declare(strict_types=1);

namespace MigrationModule\Application\Throttling;

final class ThrottlingService
{
    private int $rpm;
    private int $lastRequestAt = 0;
    private int $windowStartedAt;
    private int $requestsInWindow = 0;
    private int $backoffMs = 0;

    public function __construct(string $profile = 'balanced')
    {
        $this->rpm = match ($profile) {
            'conservative' => 20,
            'fast' => 80,
            default => 40,
        };
        $this->windowStartedAt = time();
    }

    public function currentRpm(): int
    {
        return $this->rpm;
    }

    public function throttle(): void
    {
        $intervalMs = (int) floor(60000 / max(1, $this->rpm));
        $elapsedMs = (int) floor((microtime(true) * 1000) - $this->lastRequestAt);
        $sleepMs = max(0, $intervalMs - $elapsedMs) + $this->backoffMs;
        if ($this->lastRequestAt > 0 && $sleepMs > 0) {
            usleep($sleepMs * 1000);
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
        $this->backoffMs = min(3000, max(250, $this->backoffMs * 2));
    }

    public function registerSuccessSignal(): void
    {
        $this->backoffMs = (int) max(0, $this->backoffMs / 2);
    }
}
