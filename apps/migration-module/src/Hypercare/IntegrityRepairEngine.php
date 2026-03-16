<?php

declare(strict_types=1);

namespace MigrationModule\Hypercare;

final class IntegrityRepairEngine
{
    public function repair(array $issues, bool $dryRun = true): array
    {
        $actions = [];
        foreach ($issues as $issue) {
            $actions[] = [
                'action_id' => 'repair_' . substr(hash('sha256', (string) ($issue['issue_id'] ?? uniqid('', true))), 0, 12),
                'issue_id' => (string) ($issue['issue_id'] ?? 'unknown'),
                'action_type' => $this->resolveActionType((string) ($issue['description'] ?? ''), (string) ($issue['entity_type'] ?? 'generic')),
                'status' => $dryRun ? 'simulated' : 'executed',
                'executed_at' => (new \DateTimeImmutable('now'))->format(DATE_ATOM),
            ];
        }

        return ['dry_run' => $dryRun, 'repair_actions' => $actions];
    }

    private function resolveActionType(string $description, string $entityType): string
    {
        $description = strtolower($description);
        if (str_contains($description, 'reference')) {
            return 'restore_broken_entity_relations';
        }
        if ($entityType === 'files' || str_contains($description, 'file')) {
            return 're_sync_missing_files';
        }
        if (str_contains($description, 'permission')) {
            return 'reapply_permissions';
        }

        return 'replay_failed_migration';
    }
}
