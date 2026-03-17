<?php

declare(strict_types=1);

require_once __DIR__ . '/../apps/migration-module/bootstrap.php';
migration_module_bootstrap(dirname(__DIR__));

use MigrationModule\Support\DbConfig;

$dbConfig = DbConfig::fromRuntimeSources([], dirname(__DIR__));
$hasDbEnv = (string) ($dbConfig['name'] ?? '') !== '' && (string) ($dbConfig['user'] ?? '') !== '';

if (!$hasDbEnv) {
    require_once __DIR__ . '/installer.php';
    exit;
}

try {
    require_once __DIR__ . '/../apps/migration-module/ui/admin/index.php';
} catch (Throwable $e) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!doctype html>
    <html lang="ru">
    <head><meta charset="utf-8"><title>Migration Admin Unavailable</title></head>
    <body style="font-family:sans-serif;max-width:800px;margin:20px auto">
      <h1>Migration admin временно недоступен</h1>
      <p>Bootstrap завершился ошибкой. Проверьте доступность MySQL и runtime-конфигурацию.</p>
      <pre style="background:#f4f4f4;padding:12px;border-radius:6px;overflow:auto"><?= htmlspecialchars($e->getMessage(), ENT_QUOTES) ?></pre>
    </body>
    </html>
    <?php
}
