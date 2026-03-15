<?php

declare(strict_types=1);

use MigrationModule\Application\Hypercare\AdoptionAnalyticsEngine;
use MigrationModule\Application\Hypercare\FinalReportGenerator;
use MigrationModule\Application\Hypercare\LateWriteDetector;
use MigrationModule\Application\Hypercare\MigrationSuccessScorer;
use MigrationModule\Application\Hypercare\OptimizationAdvisor;
use MigrationModule\Application\Hypercare\PerformanceRegressionAnalyzer;
use MigrationModule\Application\Hypercare\PostMigrationIntegrityScanner;
use MigrationModule\Application\Hypercare\ReconciliationEngine;
use PHPUnit\Framework\TestCase;

final class HypercareSuiteTest extends TestCase
{
    public function testIntegrityScannerFindsRelationAndFileIssues(): void
    {
        $scanner = new PostMigrationIntegrityScanner();
        $report = $scanner->scan(
            ['deals' => [['id' => 'd1', 'contact_id' => 'c1']], 'contacts' => [['id' => 'c1']], 'files' => [['id' => 'f1', 'checksum' => 'aaa']]],
            ['deals' => [['id' => 'd1', 'contact_id' => 'c2']], 'contacts' => [['id' => 'c1']], 'files' => [['id' => 'f1', 'checksum' => 'bbb']]],
        );

        self::assertGreaterThan(0, $report['summary']['issues']);
    }

    public function testReconciliationEngineSelectsStrategies(): void
    {
        $engine = new ReconciliationEngine();
        $result = $engine->reconcile([
            ['type' => 'broken_reference'],
            ['type' => 'permission_drift'],
        ]);

        self::assertCount(2, $result['tasks']);
        self::assertSame('relink_entities', $result['tasks'][0]['strategy']);
        self::assertSame('manual_operator_decision', $result['tasks'][1]['strategy']);
    }

    public function testLateWriteDetectionFindsFreezeAndCutoverWrites(): void
    {
        $detector = new LateWriteDetector();
        $events = $detector->detect([
            ['entity' => 'deal', 'entity_id' => '1', 'changed_at' => '2025-01-01T10:00:00+00:00'],
        ], [
            'freeze_start' => '2025-01-01T09:00:00+00:00',
            'cutover_end' => '2025-01-01T11:00:00+00:00',
            'stabilization_end' => '2025-01-02T09:00:00+00:00',
        ]);

        self::assertCount(1, $events);
        self::assertSame('during_cutover', $events[0]['window']);
    }

    public function testAdoptionAnalyticsProducesScore(): void
    {
        $engine = new AdoptionAnalyticsEngine();
        $result = $engine->analyze(
            ['migrated_users' => 100, 'logged_in_users' => 80, 'active_departments' => 8, 'departments_total' => 10, 'crm_activity' => 90, 'task_activity' => 110],
            ['crm_activity' => 100, 'task_activity' => 100],
        );

        self::assertGreaterThan(0.7, $result['adoption_score']);
    }

    public function testPerformanceRegressionAndOptimizationRecommendations(): void
    {
        $analyzer = new PerformanceRegressionAnalyzer();
        $regressions = $analyzer->analyze(
            ['api_latency_ms' => 100, 'query_time_ms' => 50, 'entity_load_ms' => 80, 'file_access_ms' => 40, 'search_response_ms' => 60, 'automation_exec_ms' => 30],
            ['api_latency_ms' => 150, 'query_time_ms' => 75, 'entity_load_ms' => 85, 'file_access_ms' => 41, 'search_response_ms' => 90, 'automation_exec_ms' => 35],
        );

        $recommendations = (new OptimizationAdvisor())->recommend($regressions['regressions']);
        self::assertNotEmpty($regressions['regressions']);
        self::assertNotEmpty($recommendations);
    }

    public function testSuccessScoreAndFinalReportGeneration(): void
    {
        $score = (new MigrationSuccessScorer())->score([
            'data_integrity' => 0.95,
            'adoption' => 0.84,
            'system_health' => 0.9,
            'performance' => 0.8,
            'issue_severity' => 0.2,
        ]);

        self::assertSame('SUCCESS', $score['result']);

        $dir = 'reports/hypercare-test';
        @mkdir($dir, 0775, true);
        $report = (new FinalReportGenerator())->generate(['score' => $score], $dir);

        self::assertFileExists($report['json']);
        self::assertFileExists($report['html']);
        self::assertFileExists($report['pdf']);
        self::assertFileExists($report['docx']);

        @unlink($report['json']);
        @unlink($report['html']);
        @unlink($report['pdf']);
        @unlink($report['docx']);
        @rmdir($dir);
    }
}
