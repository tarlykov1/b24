<?php

declare(strict_types=1);

$generatedConfigPath = __DIR__ . '/../../../../config/generated-install-config.json';
if (is_file($generatedConfigPath)) {
    $generated = json_decode((string) file_get_contents($generatedConfigPath), true);
    if (is_array($generated) && isset($generated['mysql']) && is_array($generated['mysql'])) {
        $mysql = $generated['mysql'];
        $_ENV['DB_HOST'] = (string) ($mysql['host'] ?? ($_ENV['DB_HOST'] ?? '127.0.0.1'));
        $_ENV['DB_PORT'] = (string) ($mysql['port'] ?? ($_ENV['DB_PORT'] ?? '3306'));
        $_ENV['DB_NAME'] = (string) ($mysql['name'] ?? ($_ENV['DB_NAME'] ?? ''));
        $_ENV['DB_USER'] = (string) ($mysql['user'] ?? ($_ENV['DB_USER'] ?? ''));
        $_ENV['DB_PASSWORD'] = (string) ($mysql['password'] ?? ($_ENV['DB_PASSWORD'] ?? ''));
        $_ENV['DB_CHARSET'] = (string) ($mysql['charset'] ?? ($_ENV['DB_CHARSET'] ?? 'utf8mb4'));
    }
}
if ((string) ($_ENV['DB_NAME'] ?? '') === '' || (string) ($_ENV['DB_USER'] ?? '') === '') {
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
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', (string) ($_ENV['DB_HOST'] ?? '127.0.0.1'), (int) ($_ENV['DB_PORT'] ?? 3306), (string) ($_ENV['DB_NAME'] ?? ''), (string) ($_ENV['DB_CHARSET'] ?? 'utf8mb4'));
    $pdo = new PDO($dsn, (string) ($_ENV['DB_USER'] ?? ''), (string) ($_ENV['DB_PASSWORD'] ?? ''));
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
