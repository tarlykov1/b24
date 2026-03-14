<?php

declare(strict_types=1);

$db = __DIR__ . '/../../../../.prototype/migration.sqlite';
$summary = ['status' => 'not initialized', 'jobs' => 0, 'queue' => 0, 'mapped' => 0, 'diff' => 0, 'issues' => 0];
if (is_file($db)) {
    $pdo = new PDO('sqlite:' . $db);
    $summary['jobs'] = (int) $pdo->query('SELECT COUNT(*) FROM jobs')->fetchColumn();
    $summary['queue'] = (int) $pdo->query('SELECT COUNT(*) FROM queue')->fetchColumn();
    $summary['mapped'] = (int) $pdo->query('SELECT COUNT(*) FROM entity_map')->fetchColumn();
    $summary['diff'] = (int) $pdo->query('SELECT COUNT(*) FROM diff')->fetchColumn();
    $summary['issues'] = (int) $pdo->query('SELECT COUNT(*) FROM integrity_issues')->fetchColumn();
    $summary['status'] = (string) ($pdo->query('SELECT status FROM jobs ORDER BY created_at DESC LIMIT 1')->fetchColumn() ?: 'idle');
}
?>
<!doctype html>
<html lang="ru">
<head><meta charset="utf-8"><title>Migration Prototype Admin</title></head>
<body style="font-family:sans-serif;max-width:1000px;margin:20px auto">
<h1>Bitrix24 Migration Admin (operational prototype)</h1>
<p><b>Важно:</b> это исполняемый prototype со stub adapters. Не production-ready.</p>
<ul>
  <li>Job status: <b><?= htmlspecialchars($summary['status']) ?></b></li>
  <li>Last run summary: jobs=<?= $summary['jobs'] ?>, queue=<?= $summary['queue'] ?>, mapped=<?= $summary['mapped'] ?></li>
  <li>Pause/resume status отображается через status job в SQLite.</li>
</ul>
<h3>CLI actions</h3>
<p><code>validate</code>, <code>dry-run</code>, <code>plan</code>, <code>execute</code>, <code>resume</code>, <code>verify</code>, <code>report</code></p>
<h3>Diff / reconciliation</h3>
<p>Diff records: <b><?= $summary['diff'] ?></b>. Integrity issues: <b><?= $summary['issues'] ?></b>.</p>
<p style="color:#a00">Prototype limitations: нет реального Bitrix API, нет distributed runtime, нет production auth и full i18n.</p>
</body>
</html>
