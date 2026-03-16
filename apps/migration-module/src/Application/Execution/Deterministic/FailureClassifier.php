<?php

declare(strict_types=1);

namespace MigrationModule\Application\Execution\Deterministic;

final class FailureClassifier
{
    public function classify(\Throwable $e): string
    {
        $m = strtolower($e->getMessage());

        return match (true) {
            str_contains($m, 'rate') => 'rate-limit',
            str_contains($m, 'timeout'), str_contains($m, 'temporary') => 'transient',
            str_contains($m, 'permission') => 'permission',
            str_contains($m, 'conflict') => 'conflict',
            str_contains($m, 'relation') => 'dependency-missing',
            str_contains($m, 'filesystem'), str_contains($m, 'file') => 'filesystem',
            str_contains($m, 'db') => 'db-read',
            str_contains($m, 'verify') => 'verification-failed',
            default => 'permanent',
        };
    }
}
