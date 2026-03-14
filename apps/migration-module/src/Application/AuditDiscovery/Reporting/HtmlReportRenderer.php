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
        $ownership = (array) ($audit['ownership'] ?? []);
        $metrics = (array) ($ownership['metrics'] ?? []);
        $orphans = (array) ($ownership['orphans'] ?? []);
        $acl = (array) ($ownership['acl_graph'] ?? []);

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
<div class="card"><h2>Ownership & ACL Risks</h2>
<ul>
<li>files owned by inactive users: <b>{$this->num($metrics['files_owned_by_inactive_users'] ?? 0)}</b></li>
<li>tasks owned by inactive users: <b>{$this->num($metrics['tasks_owned_by_inactive_users'] ?? 0)}</b></li>
<li>ACL anomalies: <b>{$this->num($metrics['disk_acl_invalid_entries'] ?? 0)}</b></li>
<li>orphan ownership objects: <b>{$this->num(array_sum(array_map('intval', $orphans)))}</b></li>
<li>broken disk inheritance: <b>{$this->num($acl['broken_acl_inheritance'] ?? 0)}</b></li>
</ul>
</div>
<div class="card"><h2>Ownership charts data</h2>
<p>ownership distribution</p><ul>{$this->pairs((array) ($ownership['charts']['ownership_distribution'] ?? []))}</ul>
<p>file ownership by user</p><ul>{$this->pairs((array) ($ownership['charts']['file_ownership_by_user'] ?? []))}</ul>
<p>tasks by responsible user</p><ul>{$this->pairs((array) ($ownership['charts']['tasks_by_responsible_user'] ?? []))}</ul>
</div>
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
            $value = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (is_bool($v) ? ($v ? 'true' : 'false') : (string) $v);
            $items .= '<li><strong>' . htmlspecialchars((string) $k, ENT_QUOTES) . '</strong>: ' . htmlspecialchars((string) $value, ENT_QUOTES) . '</li>';
        }

        return $items;
    }

    private function pairs(array $pairs): string
    {
        $items = '';
        foreach ($pairs as $item) {
            $owner = htmlspecialchars((string) ($item['owner_id'] ?? '0'), ENT_QUOTES);
            $count = (int) ($item['count'] ?? 0);
            $items .= "<li>{$owner}: {$count}</li>";
        }

        return $items;
    }

    private function num(int|float $value): string
    {
        return number_format((float) $value, 0, '.', ' ');
    }
}
