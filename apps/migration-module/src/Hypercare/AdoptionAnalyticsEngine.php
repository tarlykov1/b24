<?php

declare(strict_types=1);

namespace MigrationModule\Hypercare;

final class AdoptionAnalyticsEngine
{
    public function analyze(array $oldUsage, array $newUsage): array
    {
        $oldDau = (float) ($oldUsage['daily_active_users'] ?? 0.0);
        $newDau = (float) ($newUsage['daily_active_users'] ?? 0.0);
        $adoptionRate = $oldDau > 0.0 ? round($newDau / $oldDau, 4) : 1.0;

        $dropOffUsers = array_values(array_diff($oldUsage['active_users'] ?? [], $newUsage['active_users'] ?? []));
        $inactiveUsers = array_values(array_diff($newUsage['all_users'] ?? [], $newUsage['active_users'] ?? []));

        return [
            'adoption_metrics' => [
                'daily_active_users' => (int) $newDau,
                'weekly_active_users' => (int) ($newUsage['weekly_active_users'] ?? 0),
                'login_frequency' => (float) ($newUsage['login_frequency'] ?? 0.0),
                'task_activity' => (int) ($newUsage['task_activity'] ?? 0),
                'crm_activity' => (int) ($newUsage['crm_activity'] ?? 0),
                'file_access' => (int) ($newUsage['file_access'] ?? 0),
                'feature_usage' => $newUsage['feature_usage'] ?? [],
                'adoption_rate' => $adoptionRate,
                'drop_off_users' => $dropOffUsers,
                'inactive_users' => $inactiveUsers,
                'department_activity' => $newUsage['department_activity'] ?? [],
                'compared_to_legacy' => [
                    'old' => $oldUsage,
                    'new' => $newUsage,
                ],
            ],
        ];
    }
}
