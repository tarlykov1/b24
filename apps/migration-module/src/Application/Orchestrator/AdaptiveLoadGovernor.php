<?php

declare(strict_types=1);

namespace MigrationModule\Application\Orchestrator;

final class AdaptiveLoadGovernor
{
    public function __construct(
        private int $sourceRps,
        private int $targetRps,
        private int $batchSize,
        private int $concurrency,
    ) {
    }

    /**
     * @param array<string,mixed> $metrics
     * @return array<string,int>
     */
    public function tune(array $metrics): array
    {
        $p95LatencyMs = (int) ($metrics['source_p95_ms'] ?? 0);
        $errorRate = (float) ($metrics['error_rate'] ?? 0.0);
        $rateLimited = (bool) ($metrics['rate_limited'] ?? false);

        if ($rateLimited || $errorRate > 0.15 || $p95LatencyMs > 1_500) {
            $this->sourceRps = max(1, (int) floor($this->sourceRps * 0.7));
            $this->targetRps = max(1, (int) floor($this->targetRps * 0.8));
            $this->batchSize = max(10, (int) floor($this->batchSize * 0.8));
            $this->concurrency = max(1, (int) floor($this->concurrency * 0.7));
        } elseif ($errorRate < 0.02 && $p95LatencyMs > 0 && $p95LatencyMs < 400) {
            $this->sourceRps = min(50, $this->sourceRps + 1);
            $this->targetRps = min(70, $this->targetRps + 2);
            $this->batchSize = min(1_000, $this->batchSize + 25);
            $this->concurrency = min(32, $this->concurrency + 1);
        }

        return [
            'source_rps' => $this->sourceRps,
            'target_rps' => $this->targetRps,
            'batch_size' => $this->batchSize,
            'concurrency' => $this->concurrency,
        ];
    }
}
