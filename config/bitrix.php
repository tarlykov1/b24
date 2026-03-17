<?php

declare(strict_types=1);

return [
    'webhook_url' => $_ENV['BITRIX_WEBHOOK_URL'] ?? '',
    'webhook_token' => $_ENV['BITRIX_WEBHOOK_TOKEN'] ?? '',
    'incremental_from' => $_ENV['BITRIX_INCREMENTAL_FROM'] ?? '',
    'rate_limit_rps' => (int) ($_ENV['BITRIX_RATE_LIMIT_RPS'] ?? 8),
    'database_readonly' => [
        'enabled' => (bool) ($_ENV['BITRIX_DB_READONLY_ENABLED'] ?? false),
        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
        'name' => $_ENV['DB_NAME'] ?? '',
        'user' => $_ENV['DB_USER'] ?? '',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
        'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
        'collation' => $_ENV['DB_COLLATION'] ?? 'utf8mb4_unicode_ci',
    ],
];
