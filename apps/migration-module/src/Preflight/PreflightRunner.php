<?php

declare(strict_types=1);

namespace MigrationModule\Preflight;

use PDO;

final class PreflightRunner
{
    public function __construct(
        private readonly CheckRegistry $registry,
        private readonly CheckContext $context,
    ) {
    }

    public function run(bool $strict = false): PreflightReport
    {
        $checks = [];
        $status = 'ok';

        foreach ($this->registry->all($this->context) as $check) {
            $result = $check->run();
            $checks[] = $result;
            if ($result->status === 'blocked') {
                $status = 'blocked';
            } elseif ($result->status === 'warning' && $status !== 'blocked') {
                $status = 'warning';
            }
        }

        if ($strict && $status === 'warning') {
            $status = 'blocked';
        }

        $summary = $this->buildSummary($checks, $strict);
        $report = new PreflightReport($status, $checks, $summary);
        $this->persist($report);

        return $report;
    }

    /** @param list<CheckResult> $checks @return array<string,mixed> */
    private function buildSummary(array $checks, bool $strict): array
    {
        $summary = [
            'strict_mode' => $strict,
            'ok' => 0,
            'warning' => 0,
            'blocked' => 0,
        ];

        foreach ($checks as $check) {
            $summary[$check->status]++;
            if ($check->name === 'entity_count_discovery') {
                $summary['source_entities'] = [
                    'users' => $check->data['users'] ?? 0,
                    'tasks' => $check->data['tasks'] ?? 0,
                    'files' => $check->data['files'] ?? 0,
                    'crm' => $check->data['crm'] ?? 0,
                ];
                $summary['estimated_runtime'] = $check->data['estimated_runtime'] ?? 'n/a';
                $summary['estimated_api_calls'] = $check->data['estimated_api_calls'] ?? 0;
                $summary['estimated_disk_usage'] = $check->data['estimated_disk_usage'] ?? 0;
            }
            if ($check->name === 'rest_rate_limit') {
                $summary['recommended_rate_limit'] = $check->data['recommended_rate_limit'] ?? null;
            }
        }

        return $summary;
    }

    private function persist(PreflightReport $report): void
    {
        $pdo = $this->context->storagePdo;
        if (!$pdo instanceof PDO) {
            return;
        }

        $pdo->exec('CREATE TABLE IF NOT EXISTS preflight_reports (id INTEGER PRIMARY KEY AUTOINCREMENT, timestamp TEXT NOT NULL, config_hash TEXT NOT NULL, result_json TEXT NOT NULL)');
        $stmt = $pdo->prepare('INSERT INTO preflight_reports(timestamp, config_hash, result_json) VALUES(:timestamp,:config_hash,:result_json)');
        $stmt->execute([
            'timestamp' => date(DATE_ATOM),
            'config_hash' => hash('sha256', (string) json_encode($this->context->config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'result_json' => (string) json_encode($report->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}
