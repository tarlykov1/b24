<?php

declare(strict_types=1);

namespace MigrationModule\Application\Report;

final class FinalReportService
{
    /** @param array<string,mixed> $payload */
    public function writeBundle(array $payload, string $dir = 'reports'): array
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $files = [
            'migration_summary.json' => $payload['migration_summary'] ?? [],
            'conflicts.json' => $payload['conflicts'] ?? [],
            'unresolved_links.json' => $payload['unresolved_links'] ?? [],
            'skipped_entities.json' => $payload['skipped_entities'] ?? [],
            'delta_sync_report.json' => $payload['delta_sync_report'] ?? [],
            'verification_report.json' => $payload['verification_report'] ?? [],
            'performance_report.json' => $payload['performance_report'] ?? [],
            'final_migration_report.json' => $this->buildFinalReport($payload),
        ];

        $written = [];
        foreach ($files as $name => $data) {
            $path = $dir . '/' . $name;
            file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $written[] = $path;
        }

        $csvPath = $dir . '/migration_summary.csv';
        file_put_contents($csvPath, $this->buildCsv((array) ($payload['migration_summary']['entities'] ?? [])));
        $written[] = $csvPath;

        return $written;
    }


    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function buildFinalReport(array $payload): array
    {
        return [
            'duration_seconds' => $payload['duration_seconds'] ?? 0,
            'totals' => [
                'users_migrated' => $payload['users_migrated'] ?? 0,
                'companies_migrated' => $payload['companies_migrated'] ?? 0,
                'contacts_migrated' => $payload['contacts_migrated'] ?? 0,
                'deals_migrated' => $payload['deals_migrated'] ?? 0,
                'tasks_migrated' => $payload['tasks_migrated'] ?? 0,
                'comments_migrated' => $payload['comments_migrated'] ?? 0,
                'files_migrated' => $payload['files_migrated'] ?? 0,
            ],
            'errors' => $payload['errors'] ?? [],
            'warnings' => $payload['warnings'] ?? [],
            'conflicts' => $payload['conflicts'] ?? [],
            'performance_metrics' => $payload['performance_report'] ?? [],
        ];
    }

    /** @param array<string,array<string,int|string>> $entities */
    private function buildCsv(array $entities): string
    {
        $lines = ["entity,total_source,total_target,matched,mismatched,missing_in_target,extra_in_target,conflicts"];
        foreach ($entities as $entity => $row) {
            $lines[] = sprintf(
                '%s,%d,%d,%d,%d,%d,%d,%d',
                $entity,
                (int) ($row['total_source'] ?? 0),
                (int) ($row['total_target'] ?? 0),
                (int) ($row['matched'] ?? 0),
                (int) ($row['mismatched'] ?? 0),
                (int) ($row['missing_in_target'] ?? 0),
                (int) ($row['extra_in_target'] ?? 0),
                (int) ($row['conflicts'] ?? 0),
            );
        }

        return implode("\n", $lines) . "\n";
    }
}
