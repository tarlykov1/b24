<?php

declare(strict_types=1);

$steps = [
    'environment_check',
    'mysql_connection',
    'schema_initialization',
    'worker_configuration',
    'admin_user',
    'finish',
];
?><!doctype html>
<html lang="ru">
<head><meta charset="utf-8"><title>MySQL Installer</title></head>
<body style="font-family:sans-serif;max-width:1000px;margin:20px auto">
  <h1>Bitrix Migration Installer (MySQL-only)</h1>
  <ol>
    <?php foreach ($steps as $step): ?>
      <li><?= htmlspecialchars($step, ENT_QUOTES) ?></li>
    <?php endforeach; ?>
  </ol>

  <h3>MySQL connection settings</h3>
  <textarea id="cfg" style="width:100%;height:280px;">
{
  "config": {
    "platform": {
      "mysql_dsn": "mysql:host=127.0.0.1;port=3306;dbname=bitrix_migration;charset=utf8mb4",
      "install_dir": "/opt/bitrix-migration",
      "log_dir": "/var/log/bitrix-migration",
      "temp_dir": "/var/tmp/bitrix-migration"
    },
    "mysql": {
      "host": "127.0.0.1",
      "port": 3306,
      "name": "bitrix_migration",
      "user": "migration_user",
      "password": "",
      "charset": "utf8mb4",
      "collation": "utf8mb4_unicode_ci"
    }
  }
}
  </textarea>
  <p>
    <button onclick="run('/install/check-connection')">Проверка подключения</button>
    <button onclick="run('/install/init-schema')">Инициализация схемы</button>
    <button onclick="run('/install/generate-config')">Сохранить рабочий конфиг</button>
  </p>
  <pre id="out"></pre>

  <script>
    async function run(path) {
      const payload = JSON.parse(document.getElementById('cfg').value || '{}');
      const r = await fetch('api.php' + path, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
      });
      document.getElementById('out').textContent = await r.text();
    }
  </script>
</body>
</html>
