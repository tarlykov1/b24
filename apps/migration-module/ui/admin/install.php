<?php

declare(strict_types=1);
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Bitrix24 Migration Installer</title>
<style>
  body{font-family:Inter,Arial,sans-serif;max-width:1000px;margin:24px auto;padding:0 12px;background:#f8fafc;color:#0f172a}
  .card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:14px;margin-bottom:14px}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  label{display:block;font-size:12px;color:#334155;margin-bottom:4px}
  input,textarea{width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:8px;box-sizing:border-box}
  button{padding:8px 10px;background:#2563eb;color:#fff;border:1px solid #2563eb;border-radius:8px;cursor:pointer}
  button.secondary{background:#fff;color:#0f172a;border-color:#cbd5e1}
  .status{padding:8px;border-radius:8px;font-size:13px;background:#f1f5f9;white-space:pre-wrap}
</style>
</head>
<body>
<h1>Bitrix Migration Installer (MySQL-only) — web-first control plane</h1>
<p>Пошаговая установка без SSH: проверки окружения, source/target, canonical config, init schema.</p>

<section class="card">
  <h2>1) Environment check</h2>
  <button onclick="callInstall('/install/environment-check')">Run environment checks</button>
  <div id="envOut" class="status">Not started</div>
</section>

<section class="card">
  <h2>2) MySQL runtime config</h2>
  <div class="grid">
    <div><label>Host</label><input id="dbHost" value="127.0.0.1"></div>
    <div><label>Port</label><input id="dbPort" value="3306"></div>
    <div><label>Database</label><input id="dbName" value="bitrix_migration"></div>
    <div><label>User</label><input id="dbUser" value="migration_user"></div>
    <div style="grid-column:1/3"><label>Password</label><input id="dbPassword" type="password" value=""></div>
  </div>
  <p>
    <button onclick="callInstall('/install/check-connection')">Test MySQL connection</button>
    <button onclick="callInstall('/install/init-schema')">Init schema</button>
  </p>
  <div id="mysqlOut" class="status">Waiting for test.</div>
</section>

<section class="card">
  <h2>3) Source/Target connection</h2>
  <div class="grid">
    <div>
      <label>Source URL</label><input id="srcUrl" placeholder="https://source.bitrix24.ru/rest/">
      <label>Source Token</label><input id="srcToken" placeholder="***">
      <button class="secondary" onclick="callInstall('/install/test-source')">Test source</button>
      <div id="srcOut" class="status">Not tested</div>
    </div>
    <div>
      <label>Target URL</label><input id="tgtUrl" placeholder="https://target.bitrix24.ru/rest/">
      <label>Target Token</label><input id="tgtToken" placeholder="***">
      <button class="secondary" onclick="callInstall('/install/test-target')">Test target</button>
      <div id="tgtOut" class="status">Not tested</div>
    </div>
  </div>
</section>

<section class="card">
  <h2>4) Save canonical config + finish</h2>
  <p>
    <button onclick="callInstall('/install/save-canonical-config')">Save canonical config</button>
    <button class="secondary" onclick="location.href='index.php'">Open operations console</button>
  </p>
  <div id="saveOut" class="status">Not saved</div>
</section>

<script>
function payload() {
  return {
    config: {
      mysql: {
        host: document.getElementById('dbHost').value,
        port: Number(document.getElementById('dbPort').value || 3306),
        name: document.getElementById('dbName').value,
        user: document.getElementById('dbUser').value,
        password: document.getElementById('dbPassword').value,
        charset: 'utf8mb4',
        collation: 'utf8mb4_unicode_ci'
      },
      source: {url: document.getElementById('srcUrl').value, token: document.getElementById('srcToken').value},
      target: {url: document.getElementById('tgtUrl').value, token: document.getElementById('tgtToken').value}
    }
  };
}

async function callInstall(path) {
  const res = await fetch('api.php' + path, {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify(payload())
  });
  const data = await res.json();
  const txt = JSON.stringify(data, null, 2);

  if (path.includes('environment')) document.getElementById('envOut').textContent = txt;
  if (path.includes('check-connection') || path.includes('init-schema')) document.getElementById('mysqlOut').textContent = txt;
  if (path.includes('test-source')) document.getElementById('srcOut').textContent = txt;
  if (path.includes('test-target')) document.getElementById('tgtOut').textContent = txt;
  if (path.includes('save-canonical-config')) document.getElementById('saveOut').textContent = txt;
}
</script>
</body>
</html>
