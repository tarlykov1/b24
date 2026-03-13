<?php

declare(strict_types=1);

namespace MigrationModule\Application\Report;

final class MigrationReportService
{
    /** @param array<string,int> $migrated */
    public function save(array $migrated, int $errors, int $warnings, float $durationSeconds, string $path = 'reports/migration_report.json'): void
    {
        $directory = \dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $report = [
            'generated_at' => date(DATE_ATOM),
            'migrated' => $migrated,
            'errors' => $errors,
            'warnings' => $warnings,
            'duration_seconds' => $durationSeconds,
        ];

        file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
