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
            $items .= '<li><strong>' . htmlspecialchars((string) $k, ENT_QUOTES) . '</strong>: ' . htmlspecialchars(is_bool($v) ? ($v ? 'true' : 'false') : (string) $v, ENT_QUOTES) . '</li>';
        }

        return $items;
    }
}
