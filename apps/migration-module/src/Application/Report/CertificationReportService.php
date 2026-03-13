<?php

declare(strict_types=1);

namespace MigrationModule\Application\Report;

final class CertificationReportService
{
    /** @param array<string,mixed> $context @param array<string,mixed> $reconciliation */
    public function generate(array $context, array $reconciliation, string $dir = 'reports'): array
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $json = $this->buildReport($context, $reconciliation);
        $html = $this->buildHtml($json);
        $pdf = $this->buildPdfPayload($json);

        $jsonPath = $dir . '/certification_report.json';
        $htmlPath = $dir . '/certification_report.html';
        $pdfPath = $dir . '/certification_report.pdf';

        file_put_contents($jsonPath, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        file_put_contents($htmlPath, $html);
        file_put_contents($pdfPath, $pdf);

        return ['json' => $jsonPath, 'html' => $htmlPath, 'pdf' => $pdfPath, 'report' => $json];
    }

    /** @param array<string,mixed> $context @param array<string,mixed> $reconciliation @return array<string,mixed> */
    public function buildReport(array $context, array $reconciliation): array
    {
        $metrics = $reconciliation['certification_metrics'] ?? [];
        $counts = $reconciliation['levels']['counts']['groups'] ?? [];

        return [
            'summary' => [
                'migration_date' => $context['migration_date'] ?? date(DATE_ATOM),
                'tool_version' => $context['tool_version'] ?? 'unknown',
                'source_portal' => $context['source_portal'] ?? 'unknown',
                'target_portal' => $context['target_portal'] ?? 'unknown',
                'data_volume' => $this->dataVolume($counts),
            ],
            'migration_statistics' => [
                'transferred' => $context['transferred'] ?? 0,
                'updated' => $context['updated'] ?? 0,
                'skipped' => $context['skipped'] ?? 0,
                'self_healed' => $context['self_healed'] ?? 0,
            ],
            'reconciliation_results' => $counts,
            'errors' => [
                'unresolved_issues' => $reconciliation['anomalies'] ?? [],
                'quarantine_entities' => $context['quarantine_entities'] ?? [],
                'manual_review_items' => $context['manual_review_items'] ?? [],
            ],
            'integrity_results' => [
                'relations' => $reconciliation['levels']['relations'] ?? [],
                'stages' => $reconciliation['levels']['stages'] ?? [],
                'fields' => $reconciliation['levels']['key_fields'] ?? [],
                'files' => $reconciliation['levels']['files'] ?? [],
            ],
            'certification_score' => [
                'data_completeness' => $this->formatPercent((float) ($metrics['data_completeness'] ?? 0.0)),
                'relation_integrity' => $this->formatPercent((float) ($metrics['relation_integrity'] ?? 0.0)),
                'field_accuracy' => $this->formatPercent((float) ($metrics['field_accuracy'] ?? 0.0)),
                'file_integrity' => $this->formatPercent((float) ($metrics['file_integrity'] ?? 0.0)),
                'overall_score' => $this->formatPercent((float) ($metrics['overall_score'] ?? 0.0)),
                'certification_status' => !empty($metrics['is_certified']) ? 'Migration Certified' : 'Certification Failed',
            ],
            'recommendations' => $this->recommendations($reconciliation, $context),
            'repair_cycle' => $reconciliation['repair_cycle'] ?? [],
        ];
    }

    private function dataVolume(array $counts): int
    {
        $total = 0;
        foreach ($counts as $count) {
            $total += (int) ($count['source_count'] ?? 0);
        }

        return $total;
    }

    private function formatPercent(float $score): string
    {
        return number_format($score * 100, 2) . '%';
    }

    /** @param array<string,mixed> $report */
    private function buildHtml(array $report): string
    {
        $score = $report['certification_score'];
        return '<!doctype html><html><head><meta charset="utf-8"><title>Certification Report</title></head><body>'
            . '<h1>Migration Certification Report</h1>'
            . '<h2>Summary</h2><pre>' . htmlspecialchars(json_encode($report['summary'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES) . '</pre>'
            . '<h2>Certification Score</h2><ul>'
            . '<li>Data completeness: ' . $score['data_completeness'] . '</li>'
            . '<li>Relation integrity: ' . $score['relation_integrity'] . '</li>'
            . '<li>Field accuracy: ' . $score['field_accuracy'] . '</li>'
            . '<li>File integrity: ' . $score['file_integrity'] . '</li>'
            . '<li>Overall score: <b>' . $score['overall_score'] . '</b> (' . $score['certification_status'] . ')</li>'
            . '</ul>'
            . '<h2>Recommendations</h2><pre>' . htmlspecialchars(json_encode($report['recommendations'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES) . '</pre>'
            . '</body></html>';
    }

    /** @param array<string,mixed> $report */
    private function buildPdfPayload(array $report): string
    {
        return "CERTIFICATION REPORT\n" . json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }

    /** @param array<string,mixed> $reconciliation @param array<string,mixed> $context @return array<int,string> */
    private function recommendations(array $reconciliation, array $context): array
    {
        $recommendations = [];
        $anomalies = $reconciliation['anomalies'] ?? [];
        if ($anomalies !== []) {
            $recommendations[] = sprintf('Run incremental sync and repair for %d detected anomalies.', count($anomalies));
        }

        $quarantine = $context['quarantine_entities'] ?? [];
        if ($quarantine !== []) {
            $recommendations[] = sprintf('Fix %d quarantined entities.', count($quarantine));
        }

        $stages = $reconciliation['levels']['stages'] ?? [];
        foreach ($stages as $stageCheck) {
            if (($stageCheck['status'] ?? 'OK') !== 'OK') {
                $recommendations[] = 'Review stage mapping for mismatched semantic stages.';
                break;
            }
        }

        if ($recommendations === []) {
            $recommendations[] = 'Migration quality is high. Keep periodic reconciliation monitoring enabled.';
        }

        return $recommendations;
    }
}
