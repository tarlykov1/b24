<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../apps/migration-module/src/Infrastructure/Http/AdminAuth.php';

use MigrationModule\Infrastructure\Http\AdminAuth;

$auth = new AdminAuth();
$auth->startSession();
$loggedIn = (bool) ($_SESSION['migration_admin_auth'] ?? false);
$csrf = $auth->csrfToken();

$db = __DIR__ . '/../../../../.prototype/migration.sqlite';
$summary = ['status' => 'not initialized', 'jobs' => 0, 'queue' => 0, 'mapped' => 0, 'diff' => 0, 'issues' => 0, 'done' => 0, 'failed' => 0];
$health = [
    'score' => 100,
    'workers' => 0,
    'queue_depth' => 0,
    'retry_rate' => 0.0,
    'issues' => [],
    'recovery' => ['suggested' => 'Reduce concurrency'],
];
if (is_file($db)) {
    $pdo = new PDO('sqlite:' . $db);
    $summary['jobs'] = (int) $pdo->query('SELECT COUNT(*) FROM jobs')->fetchColumn();
    $summary['queue'] = (int) $pdo->query('SELECT COUNT(*) FROM queue')->fetchColumn();
    $summary['mapped'] = (int) $pdo->query('SELECT COUNT(*) FROM entity_map')->fetchColumn();
    $summary['diff'] = (int) $pdo->query('SELECT COUNT(*) FROM diff')->fetchColumn();
    $summary['issues'] = (int) $pdo->query('SELECT COUNT(*) FROM integrity_issues')->fetchColumn();
    $summary['done'] = (int) $pdo->query("SELECT COUNT(*) FROM queue WHERE status='done'")->fetchColumn();
    $summary['failed'] = (int) $pdo->query("SELECT COUNT(*) FROM queue WHERE status='failed'")->fetchColumn();
    $summary['status'] = (string) ($pdo->query('SELECT status FROM jobs ORDER BY created_at DESC LIMIT 1')->fetchColumn() ?: 'idle');

    $health['workers'] = (int) ($pdo->query("SELECT json_extract(status, '$.worker_concurrency') FROM state WHERE entity_type='health_runtime' LIMIT 1")->fetchColumn() ?: 0);
    $health['queue_depth'] = (int) $pdo->query("SELECT COUNT(*) FROM queue WHERE status IN ('pending','retry')")->fetchColumn();
    $retryTotal = (int) $pdo->query("SELECT COUNT(*) FROM queue WHERE updated_at >= datetime('now','-5 minutes')")->fetchColumn();
    $retryCount = (int) $pdo->query("SELECT COUNT(*) FROM queue WHERE status='retry' AND updated_at >= datetime('now','-5 minutes')")->fetchColumn();
    $health['retry_rate'] = $retryTotal > 0 ? round(($retryCount / $retryTotal) * 100, 2) : 0.0;
    $health['score'] = max(0, 100 - ($health['retry_rate'] > 3 ? 8 : 0) - ($health['queue_depth'] > 400 ? 12 : 0));
    $healthIssueRows = $pdo->query("SELECT message FROM logs WHERE level IN ('warning','error') ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $health['issues'] = array_values(array_filter(array_map(static fn ($m) => is_string($m) ? $m : '', $healthIssueRows)));
}


$progress = $summary['queue'] > 0 ? min(100, (int) round(($summary['done'] / $summary['queue']) * 100)) : 0;


$auditProfile = null;
if (is_file(__DIR__ . '/../../../../.audit/migration_profile.json')) {
    $auditProfile = json_decode((string) file_get_contents(__DIR__ . '/../../../../.audit/migration_profile.json'), true);
}
?>
<!doctype html>
<html lang="ru">
<head><meta charset="utf-8"><title>Migration Admin</title></head>
<body style="font-family:sans-serif;max-width:1000px;margin:20px auto">
<h1>Bitrix24 Migration Admin</h1>
<p><a href="install.php">Open Safe Installation Wizard</a></p>
<?php if (!$loggedIn): ?>
<form method="post" action="api.php/auth/login">
    <label>Admin password: <input type="password" name="password" required></label>
    <button type="submit">Login</button>
</form>
<p>Anonymous access disabled.</p>
<?php else: ?>
<p>Admin session active. CSRF token issued for API calls.</p>
<p><code>X-CSRF-Token: <?= htmlspecialchars($csrf, ENT_QUOTES) ?></code></p>
<ul>
  <li>Status: <b><?= htmlspecialchars($summary['status']) ?></b></li>
  <li>Entities mapped: <b><?= $summary['mapped'] ?></b></li>
  <li>Diff records: <b><?= $summary['diff'] ?></b> | Integrity issues: <b><?= $summary['issues'] ?></b></li>
  <li>Worker status: done=<?= $summary['done'] ?>, failed=<?= $summary['failed'] ?></li>
</ul>
<div style="background:#eee;height:20px;width:100%;border-radius:8px;overflow:hidden">
  <div style="height:20px;width:<?= $progress ?>%;background:#3a7;color:#fff;text-align:center"><?= $progress ?>%</div>
</div>
<?php if (is_array($auditProfile)): ?>
<h3>Audit discovery</h3>
<ul>
  <li>Portal users: <b><?= (int) ($auditProfile['users']['total'] ?? 0) ?></b> (active <?= (int) ($auditProfile['users']['active'] ?? 0) ?>)</li>
  <li>Tasks: <b><?= (int) ($auditProfile['tasks']['total'] ?? 0) ?></b> | with files <?= (int) ($auditProfile['tasks']['with_files'] ?? 0) ?></li>
  <li>File volume: <b><?= (float) ($auditProfile['files']['total_size_gb'] ?? 0.0) ?> GB</b></li>
  <li>Readiness score: <b><?= (int) ($auditProfile['raw']['readiness_score'] ?? 0) ?>/100</b></li>
  <li>Risk summary: <b><?= htmlspecialchars((string) ($auditProfile['raw']['summary']['risk_level'] ?? 'UNKNOWN')) ?></b></li>
  <li>Ownership Risks: <b><?= (int) ($auditProfile['ownership']['files_owned_by_inactive_users'] ?? 0) ?></b> files (inactive owners), tasks inactive owners <?= (int) ($auditProfile['ownership']['tasks_owned_by_inactive_users'] ?? 0) ?></li>
  <li>ACL Integrity: invalid ACL entries <b><?= (int) ($auditProfile['raw']['ownership']['metrics']['disk_acl_invalid_entries'] ?? 0) ?></b></li>
  <li>Inactive Owners: total affected objects <b><?= (int) (($auditProfile['ownership']['files_owned_by_inactive_users'] ?? 0) + ($auditProfile['ownership']['tasks_owned_by_inactive_users'] ?? 0)) ?></b></li>
  <li>Orphan Objects: <b><?= (int) ($auditProfile['ownership']['orphan_files'] ?? 0) ?></b></li>
</ul>
<p><a href="../../../../.audit/report.html" target="_blank">Open HTML audit report</a> | <a href="api.php/audit/summary">API risk summary</a></p>

<h3>Attachment / Linkage Risks</h3>
<ul>
  <li>Tasks containing files: <b><?= (int) ($auditProfile['linkage']['tasks_with_attachments'] ?? 0) ?></b></li>
  <li>Comments containing files: <b><?= (int) ($auditProfile['linkage']['tasks_with_comment_attachments'] ?? 0) ?></b></li>
  <li>Multi-linked files: <b><?= (int) ($auditProfile['linkage']['multi_linked_files'] ?? 0) ?></b></li>
  <li>Orphan linkage references: <b><?= (int) ($auditProfile['linkage']['orphan_attachment_references'] ?? 0) ?></b></li>
  <li>Recommended migration mode: <b><?= htmlspecialchars((string) ($auditProfile['linkage']['recommended_attachment_strategy'] ?? 'unknown'), ENT_QUOTES) ?></b></li>
</ul>
<?php else: ?>
<p>No audit profile yet. Run <code>php bin/migration-module audit:run</code>.</p>
<?php endif; ?>

<h3>Hypercare Command Center</h3>
<ul>
  <li><a href="api.php/hypercare/status">System health</a></li>
  <li><a href="api.php/hypercare/integrity-report">Data integrity</a></li>
  <li><a href="api.php/hypercare/adoption">Adoption analytics</a></li>
  <li><a href="api.php/hypercare/performance">Performance regressions</a></li>
  <li><a href="api.php/hypercare/final-report">Final migration report</a></li>
</ul>


<h3>Migration Health Dashboard</h3>
<ul>
  <li>Migration Health Score: <b><?= (int) $health['score'] ?>%</b></li>
  <li>Worker Status: <code>workers_running=<?= (int) $health['workers'] ?></code></li>
  <li>Queue Monitor: <code>queue_depth=<?= (int) $health['queue_depth'] ?></code></li>
  <li>Retry Heatmap (last 5m): <code><?= (float) $health['retry_rate'] ?>%</code></li>
</ul>
<p>Recovery Actions</p>
<p>Detected Issue: <?= htmlspecialchars((string) ($health['issues'][0] ?? 'none'), ENT_QUOTES) ?></p>
<p>Suggested Action: <?= htmlspecialchars((string) $health['recovery']['suggested'], ENT_QUOTES) ?> <button type="button" disabled>[Apply]</button></p>

<h3>Runtime controls</h3>
<p>Кнопки API: <code>resume</code>, <code>retry failed</code>, <code>skip failed</code> доступны через <code>/jobs/action</code> с typed confirmation.</p>
<p><a href="api.php/system:check">system:check</a> | <a href="api.php/health">health</a> | <a href="api.php/ready">ready</a></p>
<?php endif; ?>
</body>
</html>
