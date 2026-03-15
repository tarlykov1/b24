<?php

declare(strict_types=1);

namespace MigrationModule\Application\Hypercare;

final class FinalReportGenerator
{
    /** @param array<string,mixed> $report */
    public function generate(array $report, string $dir): array
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $json = $dir . '/migration-completion-report.json';
        $html = $dir . '/migration-completion-report.html';
        $pdf = $dir . '/migration-completion-report.pdf';
        $docx = $dir . '/migration-completion-report.docx';

        file_put_contents($json, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        file_put_contents($html, '<html><body><h1>Migration Completion Report</h1><pre>' . htmlspecialchars((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES) . '</pre></body></html>');
        file_put_contents($pdf, "Migration Completion Report\n" . json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        file_put_contents($docx, "Migration Completion Report\n" . json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return ['json' => $json, 'html' => $html, 'pdf' => $pdf, 'docx' => $docx, 'report' => $report];
    }
}
