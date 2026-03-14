<?php

declare(strict_types=1);

namespace MigrationModule\Application\AuditDiscovery;

final class StrategyHintEngine
{
    public function build(array $profile, array $risk): array
    {
        $activeUsers = (int) ($profile['users']['active'] ?? 0);
        $totalUsers = (int) ($profile['users']['total'] ?? 0);
        $filesGb = (float) ($profile['files']['total_size_gb'] ?? 0.0);

        return [
            'migrate_users_policy' => $activeUsers > 0 && $activeUsers < $totalUsers ? 'active + owners_only' : 'all_users',
            'tasks_strategy' => ((int) ($profile['tasks']['total'] ?? 0)) > 100000 ? 'migrate_metadata_first' : 'single_pipeline',
            'files_strategy' => $filesGb > 100 ? 'separate_bulk_transfer' : 'inline_transfer',
            'delta_sync_required' => in_array($risk['risk_level'] ?? 'LOW', ['MEDIUM', 'HIGH', 'CRITICAL'], true),
            'recommended_cutoff_window' => ($risk['risk_level'] ?? 'LOW') === 'LOW' ? 'night' : 'weekend',
        ];
    }
}
