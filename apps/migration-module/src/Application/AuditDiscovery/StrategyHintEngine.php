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
        $multiLinked = (int) ($profile['linkage']['files_multi_linked'] ?? 0);
        $commentAttachments = (int) ($profile['linkage']['tasks_with_comment_attachments'] ?? 0);

        return [
            'migrate_users_policy' => $activeUsers > 0 && $activeUsers < $totalUsers ? 'active + owners_only' : 'all_users',
            'tasks_strategy' => ((int) ($profile['tasks']['total'] ?? 0)) > 100000 ? 'migrate_metadata_first' : 'single_pipeline',
            'files_strategy' => $filesGb > 100 ? 'separate_bulk_transfer' : 'inline_transfer',
            'delta_sync_required' => in_array($risk['risk_level'] ?? 'LOW', ['MEDIUM', 'HIGH', 'CRITICAL'], true),
            'recommended_cutoff_window' => ($risk['risk_level'] ?? 'LOW') === 'LOW' ? 'night' : 'weekend',
            'file_migration_strategy' => [
                'metadata_first' => true,
                'binary_transfer_separate' => $filesGb > 50 || $multiLinked > 500,
                'preserve_multi_links' => $multiLinked > 0,
                'comment_attachments_separate_pass' => $commentAttachments > 0,
            ],
            'task_migration_strategy' => [
                'migrate_tasks_before_attachments' => true,
                'run_attachment_reconciliation_after_tasks' => true,
            ],
            'comment_attachments_need_special_handling' => $commentAttachments > 0,
            'disk_linkage_reconciliation_required' => $multiLinked > 0,
            'copy_binary_once_rebind_many' => $multiLinked > 0,
            'separate_large_file_queue_recommended' => $filesGb > 100,
        ];
    }
}
