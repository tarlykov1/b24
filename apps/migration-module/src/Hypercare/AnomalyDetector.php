<?php

declare(strict_types=1);

namespace MigrationModule\Hypercare;

final class AnomalyDetector
{
    public function detect(array $usageSignals, array $dataSignals, array $permissionSignals): array
    {
        $anomalies = [];

        if (($usageSignals['active_users_drop_pct'] ?? 0) >= 25) {
            $anomalies[] = $this->anomaly('usage', 'critical', 'Sudden drop in active users detected.');
        }
        if (($usageSignals['tasks_created_today'] ?? 1) === 0) {
            $anomalies[] = $this->anomaly('usage', 'warning', 'Tasks are not being created.');
        }
        if (($dataSignals['missing_crm_stages'] ?? 0) > 0) {
            $anomalies[] = $this->anomaly('data', 'critical', 'Missing CRM stages detected.');
        }
        if (($dataSignals['pipeline_transition_failures'] ?? 0) > 0) {
            $anomalies[] = $this->anomaly('data', 'warning', 'Broken pipeline transitions observed.');
        }
        if (($permissionSignals['group_access_losses'] ?? 0) > 0 || ($permissionSignals['inaccessible_files'] ?? 0) > 0) {
            $anomalies[] = $this->anomaly('permission', 'critical', 'Permission drift detected after migration.');
        }

        return ['anomalies' => $anomalies, 'count' => count($anomalies)];
    }

    private function anomaly(string $type, string $severity, string $description): array
    {
        return [
            'anomaly_id' => 'an_' . substr(hash('sha256', $type . ':' . $description), 0, 10),
            'type' => $type,
            'severity' => $severity,
            'description' => $description,
            'detected_at' => (new \DateTimeImmutable('now'))->format(DATE_ATOM),
        ];
    }
}
