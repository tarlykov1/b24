<?php

declare(strict_types=1);

$generatedConfigPath = __DIR__ . '/../config/generated-install-config.json';
$hasDbEnv = (string) ($_ENV['DB_NAME'] ?? '') !== '' && (string) ($_ENV['DB_USER'] ?? '') !== '';
if (!$hasDbEnv && is_file($generatedConfigPath)) {
    $payload = json_decode((string) file_get_contents($generatedConfigPath), true);
    if (is_array($payload) && isset($payload['mysql']) && is_array($payload['mysql'])) {
        foreach (['host' => 'DB_HOST', 'port' => 'DB_PORT', 'name' => 'DB_NAME', 'user' => 'DB_USER', 'password' => 'DB_PASSWORD', 'charset' => 'DB_CHARSET', 'collation' => 'DB_COLLATION'] as $key => $env) {
            if (isset($payload['mysql'][$key])) {
                $_ENV[$env] = (string) $payload['mysql'][$key];
            }
        }
        $hasDbEnv = (string) ($_ENV['DB_NAME'] ?? '') !== '' && (string) ($_ENV['DB_USER'] ?? '') !== '';
    }
}

if (!$hasDbEnv) {
    require_once __DIR__ . '/installer.php';
    exit;
}

require_once __DIR__ . '/../apps/migration-module/ui/admin/index.php';
