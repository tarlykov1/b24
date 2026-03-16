<?php

declare(strict_types=1);

namespace MigrationModule\Application\Execution\Deterministic;

final class RetryPolicy
{
    public function __construct(private readonly int $maxRetries = 3, private readonly int $baseBackoffMs = 100)
    {
    }

    public function shouldRetry(string $class, int $attempt): bool
    {
        if ($attempt >= $this->maxRetries) {
            return false;
        }

        return in_array($class, ['transient', 'rate-limit', 'db-read', 'rest-write', 'filesystem'], true);
    }

    public function backoffMs(int $attempt): int
    {
        return $this->baseBackoffMs * (2 ** max(0, $attempt - 1));
    }
}
