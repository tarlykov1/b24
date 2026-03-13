<?php

declare(strict_types=1);

namespace MigrationModule\Application\Preflight;

final class PreflightService
{
    /** @return array{status: string, checks: array<int, array<string, mixed>>} */
    public function run(): array
    {
        // TODO: execute mandatory readiness checks and block on FAIL.
        return ['status' => 'todo', 'checks' => []];
    }
}
