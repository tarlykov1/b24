<?php

declare(strict_types=1);

namespace MigrationModule\Application\Orchestrator;

final class SelfHealingLayer
{
    /**
     * @param array<string,mixed> $failure
     * @return array{strategy:string,requeue:bool,delay_ms:int,quarantine:bool}
     */
    public function heal(array $failure): array
    {
        $code = (string) ($failure['code'] ?? 'unknown');
        $attempt = (int) ($failure['attempt'] ?? 1);

        return match ($code) {
            'rate_limit', 'http_429' => ['strategy' => 'cooldown_backoff', 'requeue' => true, 'delay_ms' => min(60_000, 1_000 * (2 ** $attempt)), 'quarantine' => false],
            'transient_network', 'http_503', 'timeout' => ['strategy' => 'retry_with_jitter', 'requeue' => true, 'delay_ms' => min(120_000, 2_000 * $attempt), 'quarantine' => false],
            'missing_dependency' => ['strategy' => 'defer_until_dependency', 'requeue' => true, 'delay_ms' => 30_000, 'quarantine' => false],
            'schema_mismatch', 'duplicate_entity' => ['strategy' => 'quarantine_and_manual_review', 'requeue' => false, 'delay_ms' => 0, 'quarantine' => true],
            default => ['strategy' => 'bounded_retry', 'requeue' => $attempt <= 3, 'delay_ms' => 5_000 * $attempt, 'quarantine' => $attempt > 3],
        };
    }
}
