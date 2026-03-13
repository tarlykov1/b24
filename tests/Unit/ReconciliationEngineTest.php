<?php

declare(strict_types=1);

use MigrationModule\Application\Reconciliation\ReconciliationEngineService;
use MigrationModule\Application\Report\CertificationReportService;
use MigrationModule\Infrastructure\Persistence\MigrationRepository;
use PHPUnit\Framework\TestCase;

final class ReconciliationEngineTest extends TestCase
{
    public function testEngineBuildsMultiLevelVerificationAndCertificationMetrics(): void
    {
        $repo = new MigrationRepository();
        $jobId = $repo->beginJob('verification');
        $repo->saveMapping($jobId, 'deal', '10', '10');
        $repo->saveMapping($jobId, 'company', '1', '1');

        $source = [
            'deals' => [['id' => '10', 'title' => 'A', 'stage_id' => 'NEW', 'amount' => 1000, 'company_id' => '1']],
            'companies' => [['id' => '1', 'title' => 'Acme']],
            'files' => [['id' => 'f1', 'checksum' => 'abc', 'size' => 10, 'mime' => 'text/plain']],
            'tasks' => [['id' => 't1', 'crm_deal_id' => '10', 'created_date' => '2025-01-01T10:00:00+00:00']],
            'comments' => [['id' => 'c1', 'author_id' => 'u1', 'entity_id' => '10', 'created_date' => '2025-01-01T10:00:00+00:00']],
            'activities' => [['id' => 'a1', 'owner_id' => '10', 'entity_id' => '10', 'created_date' => '2025-01-01T10:00:00+00:00']],
        ];

        $target = [
            'deals' => [['id' => '10', 'title' => 'A', 'stage_id' => 'IN_PROGRESS', 'amount' => 1000, 'company_id' => '1']],
            'companies' => [['id' => '1', 'title' => 'Acme']],
            'files' => [['id' => 'f1', 'checksum' => 'abc', 'size' => 10, 'mime' => 'text/plain']],
            'tasks' => [['id' => 't1', 'crm_deal_id' => '10', 'created_date' => '2025-01-01T10:00:00+00:00']],
            'comments' => [['id' => 'c1', 'author_id' => 'u1', 'entity_id' => '10', 'created_date' => '2025-01-01T10:00:00+00:00']],
            'activities' => [['id' => 'a1', 'owner_id' => '10', 'entity_id' => '10', 'created_date' => '2025-01-01T10:00:00+00:00']],
        ];

        $engine = new ReconciliationEngineService($repo);
        $result = $engine->run($jobId, $source, $target, ['stage_mapping' => ['NEW' => 'IN_PROGRESS']]);

        self::assertArrayHasKey('counts', $result['levels']);
        self::assertArrayHasKey('mapping', $result['levels']);
        self::assertArrayHasKey('relations', $result['levels']);
        self::assertSame('OK', $result['levels']['stages'][0]['status']);
        self::assertArrayHasKey('overall_score', $result['certification_metrics']);
        self::assertSame('reconciliation → repair → reconciliation', $result['repair_cycle']['cycle']);
    }

    public function testCertificationReportIsGeneratedInAllFormats(): void
    {
        $service = new CertificationReportService();
        $dir = 'reports/certification-test';
        @mkdir($dir, 0775, true);

        $reconciliation = [
            'levels' => ['counts' => ['groups' => ['deals' => ['source_count' => 100, 'target_count' => 100, 'difference' => 0, 'status' => 'OK']]]],
            'certification_metrics' => ['data_completeness' => 0.999, 'relation_integrity' => 1.0, 'field_accuracy' => 0.995, 'file_integrity' => 1.0, 'overall_score' => 0.998, 'is_certified' => true],
            'anomalies' => [],
        ];

        $result = $service->generate(['tool_version' => 'test'], $reconciliation, $dir);

        self::assertFileExists($result['json']);
        self::assertFileExists($result['html']);
        self::assertFileExists($result['pdf']);
        self::assertSame('Migration Certified', $result['report']['certification_score']['certification_status']);

        @unlink($result['json']);
        @unlink($result['html']);
        @unlink($result['pdf']);
        @rmdir($dir);
    }
}
