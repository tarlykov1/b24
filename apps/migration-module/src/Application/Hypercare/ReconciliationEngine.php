<?php

declare(strict_types=1);

namespace MigrationModule\Application\Hypercare;

final class ReconciliationEngine
{
    /** @param list<array<string,mixed>> $issues
     * @return array<string,mixed>
     */
    public function reconcile(array $issues): array
    {
        $tasks = [];
        $log = [];

        foreach ($issues as $issue) {
            $strategy = $this->strategyFor((string) ($issue['type'] ?? 'unknown'));
            $status = in_array($strategy, ['auto_repair', 'retry_migration', 'relink_entities'], true) ? 'resolved' : 'queued_manual';
            $task = ['issue' => $issue, 'strategy' => $strategy, 'status' => $status];
            $tasks[] = $task;
            $log[] = ['operation' => 'reconciliation', 'strategy' => $strategy, 'issue_type' => (string) ($issue['type'] ?? 'unknown'), 'result' => $status];
        }

        return ['tasks' => $tasks, 'log' => $log, 'resolved' => count(array_filter($tasks, static fn (array $t): bool => $t['status'] === 'resolved'))];
    }

    private function strategyFor(string $type): string
    {
        return match ($type) {
            'broken_reference', 'missing_reference', 'missing_relation' => 'relink_entities',
            'lost_file', 'file_checksum_mismatch' => 'retry_migration',
            'permission_drift', 'user_reassignment_issue' => 'manual_operator_decision',
            default => 'auto_repair',
        };
    }
}
