<?php

declare(strict_types=1);

use MigrationModule\Hypercare\AdoptionAnalyticsEngine;
use MigrationModule\Hypercare\AdoptionRiskEngine;
use MigrationModule\Hypercare\HypercareMonitor;
use MigrationModule\Hypercare\HypercareScheduler;
use MigrationModule\Hypercare\IntegrityRepairEngine;
use MigrationModule\Hypercare\OptimizationEngine;
use MigrationModule\Hypercare\PerformanceMonitor;
use PHPUnit\Framework\TestCase;

final class PostMigrationOptimizationSuiteTest extends TestCase
{
    public function testHypercareMonitorProducesStructuredIssues(): void
    {
        $result = (new HypercareMonitor())->scan(
            ['users' => [['id' => 'u1']], 'contacts' => [['id' => 'c1']], 'deals' => [['id' => 'd1', 'contact_id' => 'c1']], 'files' => [['id' => 'f1', 'disk_object_id' => 'do1']]],
            ['users' => [], 'contacts' => [['id' => 'c1']], 'deals' => [['id' => 'd1', 'contact_id' => 'c404']], 'files' => [['id' => 'f1', 'disk_object_id' => 'do2']]],
        );

        self::assertNotEmpty($result['hypercare_issues']);
        self::assertArrayHasKey('issue_id', $result['hypercare_issues'][0]);
        self::assertArrayHasKey('repair_status', $result['hypercare_issues'][0]);
    }

    public function testAdoptionAnalyticsAndRiskReports(): void
    {
        $analytics = (new AdoptionAnalyticsEngine())->analyze(
            ['daily_active_users' => 100, 'active_users' => ['u1', 'u2']],
            ['daily_active_users' => 70, 'weekly_active_users' => 90, 'active_users' => ['u1'], 'all_users' => ['u1', 'u2', 'u3'], 'department_activity' => ['Sales' => ['crm_usage_drop_pct' => 35, 'task_activity_drop_pct' => 5]]],
        );

        self::assertSame(0.7, $analytics['adoption_metrics']['adoption_rate']);
        self::assertContains('u2', $analytics['adoption_metrics']['drop_off_users']);

        $risk = (new AdoptionRiskEngine())->analyze($analytics['adoption_metrics']['department_activity']);
        self::assertNotEmpty($risk['adoption_risk_reports']);
    }

    public function testPerformanceMonitoringRepairAndOptimization(): void
    {
        $perf = (new PerformanceMonitor())->monitor(['rest_latency_ms' => 1600, 'file_download_latency_ms' => 2500]);
        self::assertNotEmpty($perf['alerts']);

        $repairs = (new IntegrityRepairEngine())->repair([
            ['issue_id' => 'a', 'description' => 'Broken reference detected', 'entity_type' => 'deals'],
            ['issue_id' => 'b', 'description' => 'Missing file attachment', 'entity_type' => 'files'],
        ], true);
        self::assertSame('simulated', $repairs['repair_actions'][0]['status']);

        $optimizations = (new OptimizationEngine())->analyze(['unused_pipelines' => 1, 'duplicate_files' => 2]);
        self::assertNotEmpty($optimizations['optimization_recommendations']);
    }

    public function testHypercareSchedulerTransitionsToCompleted(): void
    {
        $status = (new HypercareScheduler())->status('2025-01-01T00:00:00+00:00', 7, '2025-01-10T00:00:00+00:00');
        self::assertSame('migration_completed', $status['mode']);
        self::assertSame(7, $status['policy']['duration_days']);
    }
}
