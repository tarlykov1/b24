<?php

declare(strict_types=1);

namespace MigrationModule\Application\Hypercare;

final class AdoptionAnalyticsEngine
{
    /** @param array<string,int|float> $signals
     * @param array<string,int|float> $baseline
     * @return array<string,mixed>
     */
    public function analyze(array $signals, array $baseline): array
    {
        $migratedUsers = max(1, (int) ($signals['migrated_users'] ?? 1));
        $loggedIn = (int) ($signals['logged_in_users'] ?? 0);
        $activeDepartments = (int) ($signals['active_departments'] ?? 0);
        $departments = max(1, (int) ($signals['departments_total'] ?? 1));
        $crmDelta = ((float) ($signals['crm_activity'] ?? 0.0)) / max(1.0, (float) ($baseline['crm_activity'] ?? 1.0));
        $taskDelta = ((float) ($signals['task_activity'] ?? 0.0)) / max(1.0, (float) ($baseline['task_activity'] ?? 1.0));

        $score = (($loggedIn / $migratedUsers) * 0.35) + (($activeDepartments / $departments) * 0.25) + (min(1.2, $crmDelta) / 1.2 * 0.2) + (min(1.2, $taskDelta) / 1.2 * 0.2);

        return [
            'metrics' => [
                'login_rate' => round($loggedIn / $migratedUsers, 4),
                'department_adoption' => round($activeDepartments / $departments, 4),
                'crm_usage_delta' => round($crmDelta, 4),
                'task_activity_delta' => round($taskDelta, 4),
            ],
            'anomalies' => array_values(array_filter([
                $activeDepartments / $departments < 0.5 ? 'departments_not_logging_in' : null,
                $crmDelta < 0.6 ? 'crm_module_underused' : null,
                $taskDelta < 0.5 ? 'task_feature_abandoned' : null,
            ])),
            'adoption_score' => round(min(1.0, $score), 4),
        ];
    }
}
