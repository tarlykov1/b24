<?php

declare(strict_types=1);

namespace MigrationModule\Preflight;

final class PreflightReport
{
    /** @param list<CheckResult> $checks @param array<string,mixed> $summary */
    public function __construct(
        public readonly string $status,
        public readonly array $checks,
        public readonly array $summary,
    ) {
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'checks' => array_map(static fn (CheckResult $check): array => $check->toArray(), $this->checks),
            'summary' => $this->summary,
        ];
    }

    public function exitCode(): int
    {
        return match ($this->status) {
            'ok' => 0,
            'warning' => 1,
            default => 2,
        };
    }
}
