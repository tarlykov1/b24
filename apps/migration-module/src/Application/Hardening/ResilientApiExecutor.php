<?php

declare(strict_types=1);

namespace MigrationModule\Application\Hardening;

use RuntimeException;
use Throwable;

final class ResilientApiExecutor
{
    private int $consecutiveFailures = 0;
    private int $circuitOpenedAt = 0;

    /** @var array<string,float|int> */
    private array $metrics = [
        'throughput' => 0,
        'api_latency_ms' => 0.0,
        'error_rate' => 0.0,
        'retry_rate' => 0.0,
        'total_requests' => 0,
        'errors' => 0,
        'retries' => 0,
    ];

    public function __construct(
        private readonly int $maxRetries = 4,
        private readonly int $baseDelayMs = 100,
        private readonly int $timeoutMs = 5000,
        private readonly int $circuitThreshold = 3,
        private readonly int $circuitCooldownSeconds = 2,
    ) {
    }

    /** @return mixed */
    public function execute(callable $request)
    {
        if ($this->isCircuitOpen()) {
            throw new RuntimeException('Circuit breaker is open; reducing load');
        }

        $attempt = 0;
        $started = microtime(true);

        while (true) {
            $attempt++;
            $this->metrics['total_requests']++;

            try {
                $result = $this->executeWithTimeout($request);
                $this->consecutiveFailures = 0;
                $this->recordLatency($started);
                $this->updateRates();

                return $result;
            } catch (Throwable $exception) {
                $this->metrics['errors']++;
                $this->consecutiveFailures++;

                if ($this->isRetriable($exception) && $attempt <= $this->maxRetries) {
                    $this->metrics['retries']++;
                    usleep($this->exponentialBackoff($attempt) * 1000);
                    continue;
                }

                if ($this->consecutiveFailures >= $this->circuitThreshold) {
                    $this->circuitOpenedAt = time();
                }
                $this->updateRates();
                throw $exception;
            }
        }
    }

    /** @return array<string,float|int> */
    public function metrics(): array
    {
        return $this->metrics;
    }

    private function executeWithTimeout(callable $request): mixed
    {
        $start = microtime(true);
        $result = $request();
        $elapsed = (int) round((microtime(true) - $start) * 1000);

        if ($elapsed > $this->timeoutMs) {
            throw new RuntimeException('network timeout');
        }

        if (is_array($result) && (($result['status'] ?? 200) >= 400)) {
            throw new RuntimeException('api_http_' . (int) $result['status']);
        }

        if (is_array($result) && (($result['partial'] ?? false) === true)) {
            throw new RuntimeException('partial response');
        }

        return $result;
    }

    private function isRetriable(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, '429')
            || str_contains($message, '500')
            || str_contains($message, 'timeout')
            || str_contains($message, 'network')
            || str_contains($message, 'partial');
    }

    private function exponentialBackoff(int $attempt): int
    {
        return $this->baseDelayMs * (2 ** ($attempt - 1));
    }

    private function isCircuitOpen(): bool
    {
        if ($this->circuitOpenedAt === 0) {
            return false;
        }

        if ((time() - $this->circuitOpenedAt) >= $this->circuitCooldownSeconds) {
            $this->circuitOpenedAt = 0;
            $this->consecutiveFailures = 0;

            return false;
        }

        return true;
    }

    private function recordLatency(float $started): void
    {
        $this->metrics['throughput']++;
        $this->metrics['api_latency_ms'] = (microtime(true) - $started) * 1000;
    }

    private function updateRates(): void
    {
        $total = max(1, (int) $this->metrics['total_requests']);
        $this->metrics['error_rate'] = $this->metrics['errors'] / $total;
        $this->metrics['retry_rate'] = $this->metrics['retries'] / $total;
    }
}
