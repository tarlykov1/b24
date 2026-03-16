<?php

declare(strict_types=1);

namespace MigrationModule\Hypercare;

final class PostMigrationOptimizationSuite
{
    public function run(array $source, array $target, array $oldUsage, array $newUsage, array $performance): array
    {
        $monitor = new HypercareMonitor();
        $adoptionEngine = new AdoptionAnalyticsEngine();
        $anomalyDetector = new AnomalyDetector();
        $performanceMonitor = new PerformanceMonitor();
        $repairEngine = new IntegrityRepairEngine();
        $optimizationEngine = new OptimizationEngine();

        $integrity = $monitor->scan($source, $target);
        $adoption = $adoptionEngine->analyze($oldUsage, $newUsage);
        $anomalies = $anomalyDetector->detect(
            [
                'active_users_drop_pct' => (100 - ($adoption['adoption_metrics']['adoption_rate'] * 100)),
                'tasks_created_today' => (int) ($newUsage['task_activity'] ?? 0),
            ],
            ['missing_crm_stages' => (int) ($newUsage['missing_crm_stages'] ?? 0)],
            ['inaccessible_files' => (int) ($newUsage['inaccessible_files'] ?? 0)],
        );
        $perf = $performanceMonitor->monitor($performance);
        $repairs = $repairEngine->repair($integrity['hypercare_issues'], true);
        $optimization = $optimizationEngine->analyze($newUsage['optimization_signals'] ?? []);

        return [
            'hypercare_issues' => $integrity['hypercare_issues'],
            'adoption_metrics' => $adoption['adoption_metrics'],
            'anomalies' => $anomalies['anomalies'],
            'performance_metrics' => $perf['performance_metrics'],
            'repair_actions' => $repairs['repair_actions'],
            'optimization_recommendations' => $optimization['optimization_recommendations'],
            'hypercare_logs' => [
                ['type' => 'monitor_events', 'payload' => $integrity['summary']],
                ['type' => 'repair_events', 'payload' => ['count' => count($repairs['repair_actions'])]],
                ['type' => 'analytics_results', 'payload' => ['adoption_rate' => $adoption['adoption_metrics']['adoption_rate']]],
                ['type' => 'alerts', 'payload' => ['count' => count($perf['alerts'])]],
            ],
        ];
    }
}
