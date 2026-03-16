<?php

declare(strict_types=1);

namespace MigrationModule\Hypercare;

final class HypercareDashboard
{
    public function build(array $healthScores, array $charts, array $issues, array $adoption): array
    {
        return [
            'migration_health_score' => [
                'data_integrity_score' => (float) ($healthScores['data_integrity_score'] ?? 0.0),
                'adoption_score' => (float) ($healthScores['adoption_score'] ?? 0.0),
                'system_performance_score' => (float) ($healthScores['system_performance_score'] ?? 0.0),
                'error_rate' => (float) ($healthScores['error_rate'] ?? 0.0),
            ],
            'live_charts' => [
                'active_users' => $charts['active_users'] ?? [],
                'task_activity' => $charts['task_activity'] ?? [],
                'crm_operations' => $charts['crm_operations'] ?? [],
                'api_latency' => $charts['api_latency'] ?? [],
                'error_rates' => $charts['error_rates'] ?? [],
            ],
            'issue_heatmap' => [
                'entity_problems' => $issues['entity_problems'] ?? [],
                'departments_affected' => $issues['departments_affected'] ?? [],
                'severity_distribution' => $issues['severity_distribution'] ?? [],
            ],
            'adoption_map' => [
                'high_usage_departments' => $adoption['high_usage_departments'] ?? [],
                'low_usage_departments' => $adoption['low_usage_departments'] ?? [],
                'inactive_users' => $adoption['inactive_users'] ?? [],
            ],
        ];
    }
}
