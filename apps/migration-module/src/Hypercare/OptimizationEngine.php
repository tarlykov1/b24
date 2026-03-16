<?php

declare(strict_types=1);

namespace MigrationModule\Hypercare;

final class OptimizationEngine
{
    public function analyze(array $signals): array
    {
        $recommendations = [];

        if (($signals['unused_pipelines'] ?? 0) > 0 || ($signals['duplicated_crm_fields'] ?? 0) > 0) {
            $recommendations[] = $this->rec('crm', 'Consolidate unused CRM pipelines and duplicate fields.');
        }
        if (($signals['task_overload_users'] ?? 0) > 0 || ($signals['inactive_groups'] ?? 0) > 0) {
            $recommendations[] = $this->rec('tasks', 'Balance task load and archive inactive groups/projects.');
        }
        if (($signals['duplicate_files'] ?? 0) > 0 || ($signals['unused_storage_mb'] ?? 0) > 500) {
            $recommendations[] = $this->rec('disk', 'Deduplicate files and reclaim unused Bitrix Disk storage.');
        }

        return ['optimization_recommendations' => $recommendations];
    }

    private function rec(string $domain, string $description): array
    {
        return [
            'recommendation_id' => 'opt_' . substr(hash('sha256', $domain . ':' . $description), 0, 12),
            'domain' => $domain,
            'description' => $description,
            'created_at' => (new \DateTimeImmutable('now'))->format(DATE_ATOM),
        ];
    }
}
