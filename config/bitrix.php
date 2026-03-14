<?php

declare(strict_types=1);

return [
    'webhook_url' => $_ENV['BITRIX_WEBHOOK_URL'] ?? '',
    'webhook_token' => $_ENV['BITRIX_WEBHOOK_TOKEN'] ?? '',
    'incremental_from' => $_ENV['BITRIX_INCREMENTAL_FROM'] ?? '',
    'rate_limit_rps' => (int) ($_ENV['BITRIX_RATE_LIMIT_RPS'] ?? 8),
    'database_readonly' => [
        'enabled' => (bool) ($_ENV['BITRIX_DB_READONLY_ENABLED'] ?? false),
        'dsn' => $_ENV['BITRIX_DB_DSN'] ?? '',
        'user' => $_ENV['BITRIX_DB_USER'] ?? '',
        'password' => $_ENV['BITRIX_DB_PASSWORD'] ?? '',
    ],
];
