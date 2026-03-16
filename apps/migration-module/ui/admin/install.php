<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../apps/migration-module/src/Infrastructure/Http/AdminAuth.php';

use MigrationModule\Infrastructure\Http\AdminAuth;

$auth = new AdminAuth();
$auth->startSession();
$loggedIn = (bool) ($_SESSION['migration_admin_auth'] ?? false);
$csrf = $auth->csrfToken();
$steps = [
    'welcome', 'environment', 'platform_db', 'source', 'target', 'filesystem', 'policy', 'resources', 'preflight', 'apply', 'post_validation',
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Bitrix Migration Safe Installation Wizard</title>
<style>
body{font-family:Arial,sans-serif;max-width:1100px;margin:20px auto;padding:0 12px}
.stepper{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px}
.step{padding:6px 10px;border:1px solid #ddd;border-radius:14px;background:#fafafa}
.panel{border:1px solid #e1e1e1;border-radius:8px;padding:12px;margin-bottom:12px}
.recommended{color:#0a6;font-weight:bold}
.warn{color:#b86a00}
.block{color:#b00020;font-weight:bold}
textarea{width:100%;min-height:220px}
</style>
</head>
<body>
<h1>Safe Web Installation Wizard</h1>
<p>Conservative defaults are preselected. Destructive actions are disabled by default.</p>
<?php if (!$loggedIn): ?>
<p class="block">Login required in admin console first.</p>
<?php else: ?>
<div class="stepper">
<?php foreach ($steps as $index => $step): ?>
  <div class="step"><?= ($index+1) ?>. <?= htmlspecialchars($step) ?></div>
<?php endforeach; ?>
</div>

<div class="panel">
  <h3>Draft install config (JSON)</h3>
  <p class="recommended">Recommended: keep <code>target.write_enabled=false</code> until dry-run and validation complete.</p>
  <textarea id="cfg">{
  "platform": {
    "mysql_dsn": "mysql:host=127.0.0.1;dbname=bitrix_migration;charset=utf8mb4",
    "mysql_user": "bitrix_migration",
    "mysql_password": "CHANGE_ME",
    "install_dir": "/opt/bitrix-migration",
    "log_dir": "/var/log/bitrix-migration",
    "temp_dir": "/var/lib/bitrix-migration/tmp"
  },
  "source": {"db_dsn": "mysql:host=old-db;dbname=bitrix_source;charset=utf8mb4"},
  "target": {"db_dsn": "mysql:host=127.0.0.1;dbname=bitrix_target;charset=utf8mb4", "write_enabled": false},
  "execution": {"workers": 2, "batch_size": 100}
}</textarea>
  <button onclick="run('/install/check')">Run install:check</button>
  <button onclick="run('/install/validate')">Run install:validate</button>
  <button onclick="run('/install/generate-config')">Run install:generate-config</button>
</div>

<div class="panel">
  <h3>Result</h3>
  <pre id="result">No checks run yet.</pre>
</div>

<script>
async function run(path){
  const cfg=JSON.parse(document.getElementById('cfg').value);
  const r=await fetch('api.php'+path,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':'<?= htmlspecialchars($csrf, ENT_QUOTES) ?>'},body:JSON.stringify({config:cfg})});
  const t=await r.text();
  document.getElementById('result').textContent=t;
}
</script>
<?php endif; ?>
</body>
</html>
