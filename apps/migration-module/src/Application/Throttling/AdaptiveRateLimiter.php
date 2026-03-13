<?php

declare(strict_types=1);

namespace MigrationModule\Application\Throttling;

final class AdaptiveRateLimiter
{
    /** @var array<string,int> */
    private array $currentRpm;

    /** @var array<string,int> */
    private array $minRpm;

    /** @var array<string,int> */
    private array $maxRpm;

    /** @var array<string,int> */
    private array $backoffMs = ['source' => 0, 'target' => 0, 'heavy' => 0];

    public function __construct(string $profile = 'balanced')
    {
        $limits = match ($profile) {
            'safe' => ['source' => 20, 'target' => 20, 'heavy' => 6],
            'aggressive' => ['source' => 55, 'target' => 65, 'heavy' => 12],
            default => ['source' => 35, 'target' => 40, 'heavy' => 8],
        };

        $this->currentRpm = $limits;
        $this->minRpm = ['source' => 8, 'target' => 8, 'heavy' => 2];
        $this->maxRpm = ['source' => 60, 'target' => 80, 'heavy' => 16];
    }

    public function currentRpm(string $channel): int
    {
        return $this->currentRpm[$channel] ?? 0;
    }

    public function registerFailure(string $channel, int $statusCode = 0): void
    {
        $drop = in_array($statusCode, [429, 502, 503, 504], true) ? 6 : 3;
        $this->currentRpm[$channel] = max($this->minRpm[$channel], $this->currentRpm[$channel] - $drop);
        $this->backoffMs[$channel] = min(5_000, max(200, $this->backoffMs[$channel] * 2));
    }

    public function registerSuccess(string $channel): void
    {
        $this->backoffMs[$channel] = (int) max(0, floor($this->backoffMs[$channel] / 2));
        $this->currentRpm[$channel] = min($this->maxRpm[$channel], $this->currentRpm[$channel] + 1);
    }

    public function recommendedSleepMs(string $channel): int
    {
        $rpm = max(1, $this->currentRpm[$channel]);
        $interval = (int) floor(60000 / $rpm);

        return $interval + $this->backoffMs[$channel];
    }
}
