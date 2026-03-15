<?php

declare(strict_types=1);

namespace MigrationModule\Application\Intelligence;

use MigrationModule\Application\AuditDiscovery\SourceAuditEngine;

final class MigrationIntelligenceEngine
{
    public function __construct(
        private readonly SourceAuditEngine $auditEngine = new SourceAuditEngine(),
        private readonly DependencyGraphAnalyzer $graphAnalyzer = new DependencyGraphAnalyzer(),
    ) {
    }

    /** @return array<string,mixed> */
    public function generate(?array $auditReport = null): array
    {
        $audit = $auditReport ?? $this->loadAuditReport();
        $entityVolumes = (array) ($audit['entity_counts'] ?? []);
        $customFields = (array) ($audit['custom_fields'] ?? []);
        $schema = $this->schemaStructureFromAudit($audit);
        $relationGraph = $this->buildRelationGraph($audit);
        $fileStorage = $this->fileStorageMetrics($audit);
        $userActivity = (array) ($audit['user_activity'] ?? []);
        $performanceIndicators = $this->performanceIndicators($audit);

        $graphAnalysis = $this->graphAnalyzer->analyze($relationGraph);
        $risks = $this->riskAnalysis($audit, $entityVolumes, $customFields, $relationGraph, $fileStorage);
        $recommendedMode = $this->recommendedMode($audit, $risks);

        $strategyPlan = $this->strategyPlan($audit, $entityVolumes, $graphAnalysis, $recommendedMode, $fileStorage);
        $performancePlan = $this->performancePlan($entityVolumes, $fileStorage, $performanceIndicators, $recommendedMode);

        $profiles = $this->strategyProfiles($performancePlan['recommended_settings']);

        $result = [
            'engine' => 'migration_intelligence_engine',
            'generated_at' => date(DATE_ATOM),
            'input' => [
                'audit_report' => $audit['audit_type'] ?? 'bitrix_source_discovery',
                'entity_volumes' => $entityVolumes,
                'schema_structure' => $schema,
                'custom_fields' => $customFields,
                'relation_graph' => $relationGraph,
                'file_storage_sizes' => $fileStorage,
                'user_activity_metrics' => $userActivity,
                'system_performance_indicators' => $performanceIndicators,
            ],
            'migration_strategy_plan' => $strategyPlan,
            'risk_analysis' => [
                'total' => count($risks),
                'items' => $risks,
            ],
            'performance_plan' => $performancePlan,
            'migration_graph' => [
                'dependencies' => $relationGraph,
                'analysis' => $graphAnalysis,
            ],
            'strategy_profiles' => $profiles,
            'runtime_overrides' => [
                'runtime_orchestrator' => [
                    'recommended_phase_count' => count($strategyPlan['recommended_migration_phases']),
                    'entity_migration_order' => $strategyPlan['entity_migration_order'],
                    'default_batch_size' => $strategyPlan['batch_sizes']['default'],
                ],
                'delta_engine' => [
                    'pre_cutover_sync_interval_min' => $strategyPlan['recommended_cutover_window']['delta_sync_interval_min'],
                    'prioritize_entities' => array_slice($strategyPlan['entity_migration_order'], -3),
                ],
                'worker_system' => $performancePlan['recommended_settings'],
                'safety' => [
                    'always_prioritize_safe_migration' => true,
                    'operator_adjustable' => true,
                ],
            ],
            'human_readable_plan' => $this->humanReadable($strategyPlan, $risks, $performancePlan, $recommendedMode),
        ];

        $this->persist($result);

        return $result;
    }

    /** @return array<string,mixed> */
    public function visualize(?array $auditReport = null): array
    {
        $audit = $auditReport ?? $this->loadAuditReport();
        $graph = $this->buildRelationGraph($audit);
        $analysis = $this->graphAnalyzer->analyze($graph);

        return [
            'graph' => $graph,
            'safe_order' => $analysis['safe_order'],
            'levels' => $analysis['levels'],
            'cycles' => $analysis['cycles'],
            'dot' => $this->toDot($graph),
            'human' => $this->humanGraph($analysis),
        ];
    }

    /** @return array<string,mixed> */
    private function loadAuditReport(): array
    {
        $path = '.audit/source_audit_report.json';
        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $this->auditEngine->run(true);
    }

    /** @return array<string,mixed> */
    private function schemaStructureFromAudit(array $audit): array
    {
        return [
            'crm_entities' => array_keys((array) ($audit['crm_pipelines'] ?? [])),
            'has_smart_processes' => ((int) ($audit['entity_counts']['smart_processes'] ?? 0)) > 0,
            'duplicate_indicators' => (array) ($audit['duplicate_entities'] ?? []),
        ];
    }

    /** @return array<string,list<string>> */
    private function buildRelationGraph(array $audit): array
    {
        $broken = (array) ($audit['broken_relations'] ?? []);
        $hasTaskFiles = ((int) ($broken['tasks_with_attachments'] ?? 0)) > 0 || ((int) ($audit['entity_counts']['files'] ?? 0)) > 0;

        return [
            'users' => [],
            'departments' => ['users'],
            'groups' => ['users', 'departments'],
            'crm_entities' => ['users', 'departments', 'groups'],
            'tasks' => ['users', 'groups', 'crm_entities'],
            'files' => $hasTaskFiles ? ['users', 'tasks', 'crm_entities'] : ['users'],
        ];
    }

    /** @return array<string,mixed> */
    private function fileStorageMetrics(array $audit): array
    {
        $gb = (float) ($audit['raw_audit']['files']['total_size_gb'] ?? 0);

        return [
            'total_size_gb' => $gb,
            'orphan_files' => (int) ($audit['orphan_files'] ?? 0),
            'storage_locations' => (array) (($audit['file_storage_locations']['storages'] ?? [])),
            'large_footprint' => $gb >= 100,
        ];
    }

    /** @return array<string,mixed> */
    private function performanceIndicators(array $audit): array
    {
        $complexity = (int) ($audit['migration_complexity_score'] ?? 0);

        return [
            'complexity_score' => $complexity,
            'estimated_runtime_hours' => (float) ($audit['estimated_runtime_hours'] ?? 0),
            'risk_level' => (string) ($audit['risk_summary']['risk_level'] ?? 'MEDIUM'),
            'inactive_users' => (int) ($audit['inactive_users'] ?? 0),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function riskAnalysis(array $audit, array $entityVolumes, array $customFields, array $graph, array $fileStorage): array
    {
        $risks = [];
        foreach ($entityVolumes as $entity => $count) {
            if ((int) $count >= 50000) {
                $risks[] = [
                    'type' => 'very_large_entity',
                    'severity' => 'high',
                    'affected_entities' => [$entity],
                    'mitigation_strategy' => 'Split migration into dedicated phase with lower batch size and additional checkpoints.',
                ];
            }
        }

        foreach ($graph as $entity => $deps) {
            if (count($deps) >= 3) {
                $risks[] = [
                    'type' => 'high_relation_complexity',
                    'severity' => 'medium',
                    'affected_entities' => [$entity],
                    'mitigation_strategy' => 'Migrate dependencies first and enforce referential reconciliation for this entity.',
                ];
            }
        }

        if (count($customFields) >= 80) {
            $risks[] = [
                'type' => 'custom_fields_conflict',
                'severity' => 'high',
                'affected_entities' => ['crm_entities'],
                'mitigation_strategy' => 'Pre-map custom fields, lock schema drift and run pre-cutover field compatibility verification.',
            ];
        }

        if (count((array) ($audit['crm_pipelines'] ?? [])) > 10) {
            $risks[] = [
                'type' => 'pipeline_inconsistency',
                'severity' => 'medium',
                'affected_entities' => ['crm_entities'],
                'mitigation_strategy' => 'Normalize pipeline statuses and perform dry-run transition checks before data copy.',
            ];
        }

        if (((int) ($audit['broken_relations']['orphan_attachment_references'] ?? 0)) > 0) {
            $risks[] = [
                'type' => 'broken_references',
                'severity' => 'high',
                'affected_entities' => ['tasks', 'files'],
                'mitigation_strategy' => 'Repair orphan references in source and rerun relation integrity checks.',
            ];
        }

        if (($fileStorage['large_footprint'] ?? false) || ($fileStorage['orphan_files'] ?? 0) > 0) {
            $risks[] = [
                'type' => 'file_storage_risk',
                'severity' => ($fileStorage['orphan_files'] ?? 0) > 0 ? 'high' : 'medium',
                'affected_entities' => ['files'],
                'mitigation_strategy' => 'Use resumable transfer, checksum validation, and dedicated off-peak file migration lanes.',
            ];
        }

        return $risks;
    }

    /** @param list<array<string,mixed>> $risks */
    private function recommendedMode(array $audit, array $risks): string
    {
        $highRisks = count(array_filter($risks, static fn (array $risk): bool => ($risk['severity'] ?? '') === 'high'));
        $complexity = (int) ($audit['migration_complexity_score'] ?? 0);

        if ($highRisks >= 2 || $complexity >= 70) {
            return 'SAFE_MODE';
        }

        if ($complexity >= 45) {
            return 'BALANCED_MODE';
        }

        return 'HIGH_SPEED_MODE';
    }

    /** @return array<string,mixed> */
    private function strategyPlan(array $audit, array $entityVolumes, array $graphAnalysis, string $mode, array $fileStorage): array
    {
        $batchDefault = $mode === 'SAFE_MODE' ? 100 : ($mode === 'BALANCED_MODE' ? 250 : 500);
        $concurrency = $mode === 'SAFE_MODE' ? 2 : ($mode === 'BALANCED_MODE' ? 4 : 8);
        $runtimeHours = (float) ($audit['estimated_runtime_hours'] ?? 4.0);

        return [
            'recommended_migration_phases' => [
                'phase_1_foundation_users_departments',
                'phase_2_groups_and_permissions',
                'phase_3_crm_entities',
                'phase_4_tasks_and_links',
                'phase_5_files_and_final_reconciliation',
            ],
            'entity_migration_order' => $graphAnalysis['safe_order'],
            'batch_sizes' => [
                'default' => $batchDefault,
                'files' => max(20, (int) floor($batchDefault / 5)),
                'crm_entities' => $mode === 'SAFE_MODE' ? 120 : 300,
            ],
            'concurrency_limits' => [
                'global_workers' => $concurrency,
                'max_parallel_entity_lanes' => max(1, (int) floor($concurrency / 2)),
                'source_read_qps_limit' => $mode === 'HIGH_SPEED_MODE' ? 90 : 60,
            ],
            'file_transfer_strategy' => [
                'mode' => ($fileStorage['large_footprint'] ?? false) ? 'resumable_chunked_parallel' : 'resumable_chunked',
                'checksum_validation' => true,
                'off_peak_only' => true,
            ],
            'expected_runtime_hours' => round($runtimeHours * ($mode === 'SAFE_MODE' ? 1.15 : ($mode === 'BALANCED_MODE' ? 1.0 : 0.85)), 1),
            'recommended_cutover_window' => [
                'window_type' => 'low_activity_off_peak',
                'duration_minutes' => $mode === 'SAFE_MODE' ? 120 : 90,
                'delta_sync_interval_min' => $mode === 'SAFE_MODE' ? 15 : 10,
                'final_freeze_required' => true,
            ],
            'operator_adjustable' => true,
        ];
    }

    /** @return array<string,mixed> */
    private function performancePlan(array $entityVolumes, array $fileStorage, array $performanceIndicators, string $mode): array
    {
        $totalEntities = array_sum(array_map('intval', $entityVolumes));
        $volumeGb = (float) ($fileStorage['total_size_gb'] ?? 0) + ($totalEntities / 100000);
        $throughput = $mode === 'SAFE_MODE' ? 2200.0 : ($mode === 'BALANCED_MODE' ? 4200.0 : 7200.0);
        $durationHours = round(max(1.0, $totalEntities / $throughput), 1);

        return [
            'estimate' => [
                'total_entities' => $totalEntities,
                'total_data_volume_gb' => round($volumeGb, 2),
                'estimated_throughput_entities_per_hour' => $throughput,
                'expected_duration_hours' => max($durationHours, (float) ($performanceIndicators['estimated_runtime_hours'] ?? 0)),
            ],
            'recommended_settings' => [
                'workers' => $mode === 'SAFE_MODE' ? 4 : ($mode === 'BALANCED_MODE' ? 8 : 12),
                'batch_sizes' => [
                    'users' => $mode === 'SAFE_MODE' ? 100 : 250,
                    'crm_entities' => $mode === 'SAFE_MODE' ? 120 : 320,
                    'tasks' => $mode === 'HIGH_SPEED_MODE' ? 500 : 220,
                    'files' => $mode === 'SAFE_MODE' ? 25 : 80,
                ],
                'parallelism' => [
                    'entity_lanes' => $mode === 'SAFE_MODE' ? 2 : 4,
                    'file_threads' => $mode === 'HIGH_SPEED_MODE' ? 4 : 2,
                ],
                'rate_limits' => [
                    'source_read_qps' => $mode === 'HIGH_SPEED_MODE' ? 90 : 60,
                    'target_write_qps' => $mode === 'HIGH_SPEED_MODE' ? 110 : 80,
                ],
                'safety_guardrails' => [
                    'source_load_protection_enabled' => true,
                    'auto_throttle_on_latency_spike' => true,
                ],
            ],
        ];
    }

    /** @param array<string,mixed> $baseSettings @return array<string,mixed> */
    private function strategyProfiles(array $baseSettings): array
    {
        return [
            'SAFE_MODE' => [
                'worker_count' => min(4, (int) ($baseSettings['workers'] ?? 4)),
                'throttle_limits' => ['source_read_qps' => 45, 'target_write_qps' => 65],
                'batch_sizes' => ['default' => 100, 'files' => 25],
            ],
            'BALANCED_MODE' => [
                'worker_count' => max(6, (int) ($baseSettings['workers'] ?? 8)),
                'throttle_limits' => ['source_read_qps' => 60, 'target_write_qps' => 80],
                'batch_sizes' => ['default' => 240, 'files' => 70],
            ],
            'HIGH_SPEED_MODE' => [
                'worker_count' => max(10, (int) ($baseSettings['workers'] ?? 12)),
                'throttle_limits' => ['source_read_qps' => 90, 'target_write_qps' => 110],
                'batch_sizes' => ['default' => 500, 'files' => 100],
            ],
        ];
    }

    /** @param list<array<string,mixed>> $risks */
    private function humanReadable(array $strategyPlan, array $risks, array $performancePlan, string $recommendedMode): string
    {
        $lines = [
            'Migration Intelligence Strategy Plan',
            'Recommended mode: ' . $recommendedMode,
            'Migration order: ' . implode(' -> ', $strategyPlan['entity_migration_order']),
            'Expected runtime (hours): ' . (string) $strategyPlan['expected_runtime_hours'],
            'Workers: ' . (string) $performancePlan['recommended_settings']['workers'],
            'Total entities: ' . (string) $performancePlan['estimate']['total_entities'],
            'Risk count: ' . (string) count($risks),
            'Operator can override all generated settings before execution.',
        ];

        return implode(PHP_EOL, $lines);
    }

    /** @param array<string,mixed> $result */
    private function persist(array $result): void
    {
        if (!is_dir('.audit')) {
            mkdir('.audit', 0775, true);
        }

        file_put_contents('.audit/migration_strategy_plan.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        file_put_contents('.audit/migration_strategy_plan.txt', (string) ($result['human_readable_plan'] ?? ''));
    }

    /** @param array<string,list<string>> $graph */
    private function toDot(array $graph): string
    {
        $edges = [];
        foreach ($graph as $node => $deps) {
            if ($deps === []) {
                $edges[] = sprintf('  "%s";', $node);
                continue;
            }
            foreach ($deps as $dep) {
                $edges[] = sprintf('  "%s" -> "%s";', $dep, $node);
            }
        }

        return "digraph MigrationDependencies {\n" . implode("\n", $edges) . "\n}";
    }

    /** @param array{safe_order:list<string>,levels:list<list<string>>,cycles:list<array{from:string,to:string}>} $analysis */
    private function humanGraph(array $analysis): string
    {
        $levels = array_map(static fn (array $level): string => '[' . implode(', ', $level) . ']', $analysis['levels']);
        return 'Safe order: ' . implode(' -> ', $analysis['safe_order']) . PHP_EOL
            . 'Levels: ' . implode(' | ', $levels) . PHP_EOL
            . 'Cycles: ' . (count($analysis['cycles']) === 0 ? 'none' : json_encode($analysis['cycles']));
    }
}
