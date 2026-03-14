<?php

declare(strict_types=1);

namespace MigrationModule\Application\AuditDiscovery\Reporting;

final class HtmlReportRenderer
{
    public function render(array $audit): string
    {
        $json = htmlspecialchars((string) json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES);
        $risk = htmlspecialchars((string) ($audit['summary']['risk_level'] ?? 'UNKNOWN'), ENT_QUOTES);
        $score = (int) ($audit['readiness_score'] ?? 0);
        $linkage = (array) ($audit['linkage'] ?? []);

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Bitrix Audit Report</title>
<style>
body{font-family:Arial,sans-serif;margin:20px;background:#fafafa}
.card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:12px;margin-bottom:12px}
.flag{font-weight:700;color:#b00}
.good{font-weight:700;color:#070}
pre{overflow:auto;background:#111;color:#ddd;padding:12px;border-radius:8px}
</style>
</head>
<body>
<h1>Bitrix Discovery Audit Report</h1>
<div class="card"><h2>Risk Flags</h2><p class="flag">Risk level: {$risk}</p></div>
<div class="card"><h2>Migration readiness score</h2><p class="good">{$score}/100</p></div>
<div class="card"><h2>Strategy hints</h2><ul>{$this->hintsList($audit['strategy_hints'] ?? [])}</ul></div>
<div class="card"><h2>Task / File Linkage Risks</h2><ul>{$this->linkageList($linkage)}</ul></div>
<div class="card"><h2>Linkage charts source</h2><ul>{$this->chartSourceList($linkage)}</ul></div>
<div class="card"><h2>Data tables & charts source</h2><p>JSON below is used by charts/tables in admin UI.</p></div>
<pre>{$json}</pre>
</body>
</html>
HTML;
    }

    private function hintsList(array $hints): string
    {
        $items = '';
        foreach ($hints as $k => $v) {
            $items .= '<li><strong>' . htmlspecialchars((string) $k, ENT_QUOTES) . '</strong>: ' . htmlspecialchars(is_bool($v) ? ($v ? 'true' : 'false') : (string) json_encode($v, JSON_UNESCAPED_UNICODE), ENT_QUOTES) . '</li>';
        }

        return $items;
    }

    private function linkageList(array $linkage): string
    {
        $entries = [
            'tasks with files' => (int) ($linkage['tasks_with_attachments'] ?? 0),
            'tasks with comment files' => (int) ($linkage['tasks_with_comment_attachments'] ?? 0),
            'multi-linked files' => (int) ($linkage['files_multi_linked'] ?? 0),
            'orphan attachment references' => (int) ($linkage['orphan_attachment_references'] ?? 0),
            'attachment topology complexity (types)' => count((array) ($linkage['attachment_type_distribution'] ?? [])),
        ];

        $items = '';
        foreach ($entries as $label => $value) {
            $items .= '<li><strong>' . htmlspecialchars($label, ENT_QUOTES) . '</strong>: ' . $value . '</li>';
        }

        return $items;
    }

    private function chartSourceList(array $linkage): string
    {
        return '<li>attachments per task: linkage.attachments_per_task_top</li>'
            . '<li>files by linkage type: linkage.attachment_type_distribution</li>'
            . '<li>attachment source distribution: linkage.attachment_type_distribution</li>'
            . '<li>multi-linked files distribution: linkage.raw.files_* metrics</li>';
    }
}
