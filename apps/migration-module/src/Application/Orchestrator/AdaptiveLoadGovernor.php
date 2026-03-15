<?php

declare(strict_types=1);

namespace MigrationModule\Application\Orchestrator;

use DateTimeImmutable;

final class AdaptiveLoadGovernor
{
    private string $mode = 'day';

    public function __construct(
        private int $sourceRps,
        private int $targetRps,
        private int $batchSize,
        private int $concurrency,
    ) {
    }

    /**
     * @param array<string,mixed> $metrics
     * @return array<string,int|string>
     */
    public function tune(array $metrics): array
    {
        $p95LatencyMs = (int) ($metrics['source_p95_ms'] ?? 0);
        $errorRate = (float) ($metrics['error_rate'] ?? 0.0);
        $rateLimited = (bool) ($metrics['rate_limited'] ?? false);
        $cpuLoad = (float) ($metrics['cpu_load'] ?? 0.0);
        $this->mode = $this->detectMode((string) ($metrics['runtime_mode'] ?? 'auto'));

        $modeMultiplier = $this->mode === 'night_weekend_high_speed' ? 1.2 : 1.0;

        if ($rateLimited || $errorRate > 0.1 || $p95LatencyMs > 1_200 || $cpuLoad > 0.85) {
            $this->sourceRps = max(1, (int) floor($this->sourceRps * 0.65));
            $this->targetRps = max(1, (int) floor($this->targetRps * 0.7));
            $this->batchSize = max(10, (int) floor($this->batchSize * 0.75));
            $this->concurrency = max(1, (int) floor($this->concurrency * 0.7));
        } elseif ($errorRate < 0.02 && $p95LatencyMs > 0 && $p95LatencyMs < 500 && $cpuLoad < 0.75) {
            $this->sourceRps = min((int) floor(60 * $modeMultiplier), $this->sourceRps + 2);
            $this->targetRps = min((int) floor(80 * $modeMultiplier), $this->targetRps + 3);
            $this->batchSize = min((int) floor(1_200 * $modeMultiplier), $this->batchSize + 30);
            $this->concurrency = min((int) floor(48 * $modeMultiplier), $this->concurrency + 1);
        }

        return [
            'source_rps' => $this->sourceRps,
            'target_rps' => $this->targetRps,
            'batch_size' => $this->batchSize,
            'concurrency' => $this->concurrency,
            'mode' => $this->mode,
        ];
    }

    private function detectMode(string $runtimeMode): string
    {
        if ($runtimeMode === 'day') {
            return 'day';
        }

        if ($runtimeMode === 'night_weekend_high_speed') {
            return 'night_weekend_high_speed';
        }

        $now = new DateTimeImmutable('now');
        $hour = (int) $now->format('G');
        $dayOfWeek = (int) $now->format('N');
        if ($dayOfWeek >= 6 || $hour < 7 || $hour >= 21) {
            return 'night_weekend_high_speed';
        }

        return 'day';
    }
}
