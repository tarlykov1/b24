<?php

declare(strict_types=1);

use MigrationModule\Support\DbConfig;

$dbConfig = DbConfig::fromRuntimeSources([], dirname(__DIR__, 4));
if ((string) ($dbConfig['name'] ?? '') === '' || (string) ($dbConfig['user'] ?? '') === '') {
    header('Location: install.php');
    exit;
}

$loggedIn = true;
$csrf = 'n/a';
$summary = [
    'status' => 'running',
    'mapped' => 0,
    'diff' => 0,
    'issues' => 0,
    'done' => 0,
    'failed' => 0,
    'queue' => 0,
];

try {
    $pdo = new PDO(DbConfig::dsn($dbConfig), (string) $dbConfig['user'], (string) $dbConfig['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $summary['queue'] = (int) $pdo->query('SELECT COUNT(*) FROM queue')->fetchColumn();
} catch (Throwable) {
}

$progress = $summary['queue'] > 0 ? min(100, (int) round(($summary['done'] / $summary['queue']) * 100)) : 0;
?>
<!doctype html>
<html lang="ru">
<head><meta charset="utf-8"><title>Migration Admin</title></head>
<body style="font-family:sans-serif;max-width:1000px;margin:20px auto">
<h1>Bitrix24 Migration Admin</h1>
<p><a href="install.php">Open MySQL Installation Wizard</a></p>
<p><a href="api.php/system:check">system:check</a> | <a href="api.php/health">health</a> | <a href="api.php/ready">ready</a></p>
<div style="background:#eee;height:20px;width:100%;border-radius:8px;overflow:hidden">
  <div style="height:20px;width:<?= $progress ?>%;background:#3a7;color:#fff;text-align:center"><?= $progress ?>%</div>
</div>
</body>
</html>
