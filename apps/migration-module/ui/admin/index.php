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
}

$progress = $summary['queue'] > 0 ? min(100, (int) round(($summary['done'] / $summary['queue']) * 100)) : 0;
?>
<!doctype html>
<html lang="ru">
<head><meta charset="utf-8"><title>Migration Admin</title></head>
<body style="font-family:sans-serif;max-width:1000px;margin:20px auto">
<h1>Bitrix24 Migration Admin</h1>
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
<h3>Runtime controls</h3>
<p>Кнопки API: <code>resume</code>, <code>retry failed</code>, <code>skip failed</code> доступны через <code>/jobs/action</code> с typed confirmation.</p>
<p><a href="api.php/system:check">system:check</a> | <a href="api.php/health">health</a> | <a href="api.php/ready">ready</a></p>
<?php endif; ?>
</body>
</html>
