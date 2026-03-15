<?php

declare(strict_types=1);

namespace MigrationModule\Application\AuditDiscovery;

final class SourceAuditEngine
{
    public function __construct(private readonly AuditDiscoveryService $auditService = new AuditDiscoveryService())
    {
    }

    /** @return array<string,mixed> */
    public function run(bool $deep = true): array
    {
        $raw = [
            'portal_profile' => $this->auditService->run('portal', $deep),
            'users' => $this->auditService->run('users', $deep),
            'tasks' => $this->auditService->run('tasks', $deep),
            'files' => $this->auditService->run('files', $deep),
            'crm' => $this->auditService->run('crm', $deep),
            'permissions' => $this->auditService->run('permissions', $deep),
            'linkage' => $this->auditService->run('linkage', $deep),
            'summary' => $this->auditService->run('summary', $deep),
            'strategy_hints' => [],
            'sources' => [],
        ];

        $complexity = $this->complexityScore($raw);
        $runtime = $this->estimateRuntimeHours($raw, $complexity);
        $strategy = $this->recommendedStrategy($raw, $complexity);
        $cutover = $this->cutoverRecommendations($raw, $complexity, $runtime);

        $report = [
            'audit_type' => 'bitrix_source_discovery',
            'generated_at' => date(DATE_ATOM),
            'sources' => $raw['sources'] ?? [],
            'entity_counts' => [
                'users' => (int) ($raw['users']['total'] ?? 0),
                'departments' => (int) ($raw['users']['departments_total'] ?? 0),
                'groups_projects' => (int) ($raw['portal_profile']['groups_projects_count'] ?? 0),
                'contacts' => (int) ($raw['crm']['contacts'] ?? 0),
                'companies' => (int) ($raw['crm']['companies'] ?? 0),
                'deals' => (int) ($raw['crm']['deals'] ?? 0),
                'leads' => (int) ($raw['crm']['leads'] ?? 0),
                'smart_processes' => (int) ($raw['crm']['smart_processes']['count'] ?? 0),
                'tasks' => (int) ($raw['tasks']['total'] ?? 0),
                'files' => (int) ($raw['files']['total'] ?? 0),
            ],
            'crm_pipelines' => (array) ($raw['crm']['pipelines'] ?? []),
            'custom_fields' => (array) ($raw['crm']['custom_fields'] ?? []),
            'smart_processes' => (array) ($raw['crm']['smart_processes'] ?? []),
            'file_storage_locations' => [
                'upload_path' => (string) ($_ENV['BITRIX_UPLOAD_PATH'] ?? getenv('BITRIX_UPLOAD_PATH') ?: '/upload'),
                'storages' => (array) ($raw['files']['storage_locations'] ?? []),
            ],
            'user_activity' => (array) ($raw['users']['activity'] ?? []),
            'inactive_users' => (int) ($raw['users']['inactive'] ?? 0),
            'broken_relations' => (array) ($raw['linkage'] ?? []),
            'orphan_files' => (int) ($raw['files']['orphan_files'] ?? 0),
            'duplicate_entities' => [
                'duplicate_files' => (int) ($raw['files']['duplicates']['files'] ?? 0),
                'duplicate_contacts' => (int) ($raw['crm']['duplicates']['contacts'] ?? 0),
                'duplicate_companies' => (int) ($raw['crm']['duplicates']['companies'] ?? 0),
            ],
            'migration_complexity_score' => $complexity,
            'estimated_runtime_hours' => $runtime,
            'recommended_migration_strategy' => $strategy,
            'cutover_recommendations' => $cutover,
            'risk_summary' => (array) ($raw['summary'] ?? []),
            'strategy_hints' => (array) ($raw['strategy_hints'] ?? []),
            'raw_audit' => $raw,
        ];

        $this->persist($report);

        return $report;
    }

    /** @param array<string,mixed> $report */
    private function persist(array $report): void
    {
        if (!is_dir('.audit')) {
            mkdir('.audit', 0775, true);
        }

        file_put_contents('.audit/source_audit_report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        file_put_contents('.audit/source_migration_report.md', $this->toHumanReport($report));
    }

    /** @param array<string,mixed> $raw */
    private function complexityScore(array $raw): int
    {
        $base = (int) ($raw['summary']['score'] ?? 0) * 8;
        $volume = (int) (($raw['tasks']['total'] ?? 0) / 10000) + (int) (($raw['files']['total'] ?? 0) / 20000);
        $relations = (int) (($raw['linkage']['orphan_attachment_references'] ?? 0) / 200);

        return min(100, max(1, $base + $volume + $relations));
    }

    /** @param array<string,mixed> $raw */
    private function estimateRuntimeHours(array $raw, int $complexity): float
    {
        $entities = (int) ($raw['users']['total'] ?? 0) + (int) ($raw['tasks']['total'] ?? 0) + (int) ($raw['files']['total'] ?? 0) + (int) ($raw['crm']['total'] ?? 0);
        $throughputPerHour = max(2000, 20000 - ($complexity * 100));

        return round(max(1.0, $entities / $throughputPerHour), 1);
    }

    /** @param array<string,mixed> $raw */
    private function recommendedStrategy(array $raw, int $complexity): string
    {
        if ($complexity >= 75) {
            return 'phased-by-domain-with-long-running-delta';
        }

        if (($raw['files']['total_size_gb'] ?? 0) > 100) {
            return 'parallel-crm-and-users-with-separate-file-bulk-lane';
        }

        return 'single-wave-with-short-delta-and-final-verification';
    }

    /** @param array<string,mixed> $raw @return list<string> */
    private function cutoverRecommendations(array $raw, int $complexity, float $runtime): array
    {
        $items = [];
        $items[] = $complexity >= 60 ? 'Plan weekend cutover with business owner sign-off and rollback checkpoints.' : 'Night cutover window is acceptable with standard freeze policy.';
        $items[] = $runtime > 6 ? 'Run pre-cutover delta sync every 15 minutes during the final 24 hours.' : 'Run hourly delta sync on T-1 and one final sync at freeze.';
        if ((int) ($raw['users']['inactive'] ?? 0) > 0) {
            $items[] = 'Exclude inactive users from login-enabled migration, preserve ownership mapping only.';
        }
        if ((int) ($raw['files']['orphan_files'] ?? 0) > 0) {
            $items[] = 'Repair orphan files and attachment links before production cutover.';
        }

        return $items;
    }

    /** @param array<string,mixed> $report */
    private function toHumanReport(array $report): string
    {
        return "# Bitrix Source Migration Audit\n\n"
            . "- Generated at: {$report['generated_at']}\n"
            . "- Complexity score: {$report['migration_complexity_score']}/100\n"
            . "- Estimated runtime: {$report['estimated_runtime_hours']} hours\n"
            . "- Recommended strategy: {$report['recommended_migration_strategy']}\n"
            . "\n## Entity Counts\n"
            . "- Users: {$report['entity_counts']['users']}\n"
            . "- Departments: {$report['entity_counts']['departments']}\n"
            . "- Groups/Projects: {$report['entity_counts']['groups_projects']}\n"
            . "- CRM (contacts/companies/deals/leads): {$report['entity_counts']['contacts']}/{$report['entity_counts']['companies']}/{$report['entity_counts']['deals']}/{$report['entity_counts']['leads']}\n"
            . "- Smart processes: {$report['entity_counts']['smart_processes']}\n"
            . "- Tasks: {$report['entity_counts']['tasks']}\n"
            . "- Files: {$report['entity_counts']['files']}\n"
            . "\n## Data Quality\n"
            . "- Inactive users: {$report['inactive_users']}\n"
            . "- Orphan files: {$report['orphan_files']}\n"
            . "- Duplicate files: {$report['duplicate_entities']['duplicate_files']}\n"
            . "\n## Cutover Recommendations\n"
            . implode("\n", array_map(static fn (string $line): string => '- ' . $line, $report['cutover_recommendations']))
            . "\n";
    }
}
