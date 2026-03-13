<?php

declare(strict_types=1);

namespace MigrationModule\Application\Validation;

use DateTimeImmutable;

final class MigrationReportService
{
    /**
     * @param array<string, mixed> $job
     * @param array<string, mixed> $integrity
     * @param array<string, mixed> $statistics
     * @param array<int, array<string, mixed>> $referenceProblems
     * @return array<string, mixed>
     */
    public function build(array $job, array $integrity, array $statistics, array $referenceProblems): array
    {
        return [
            'migration_summary' => [
                'started_at' => ($job['started_at'] ?? new DateTimeImmutable())->format(DATE_ATOM),
                'ended_at' => ($job['ended_at'] ?? new DateTimeImmutable())->format(DATE_ATOM),
                'mode' => (string) ($job['mode'] ?? 'initial'),
            ],
            'statistics' => $job['metrics'] ?? [],
            'integrity_check' => [
                'result' => $integrity['status'] ?? 'unknown',
                'problems' => array_merge($integrity['problems'] ?? [], $referenceProblems),
            ],
            'statistics_comparison' => $statistics,
            'performance' => [
                'average_batch_time_ms' => (float) (($job['metrics']['batch_avg_ms'] ?? 0.0)),
                'api_requests' => (int) (($job['metrics']['api_requests'] ?? 0)),
                'retry_count' => (int) (($job['metrics']['retries'] ?? 0)),
            ],
        ];
    }

    public function toJson(array $report): string
    {
        return (string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
