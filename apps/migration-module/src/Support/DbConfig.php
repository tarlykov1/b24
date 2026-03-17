<?php

declare(strict_types=1);

namespace MigrationModule\Support;

final class DbConfig
{
    public static function fromEnvAndOverride(array $override = []): array
    {
        return [
            'host' => (string) ($override['host'] ?? ($_ENV['DB_HOST'] ?? '127.0.0.1')),
            'port' => (int) ($override['port'] ?? ($_ENV['DB_PORT'] ?? 3306)),
            'name' => (string) ($override['name'] ?? ($_ENV['DB_NAME'] ?? '')),
            'user' => (string) ($override['user'] ?? ($_ENV['DB_USER'] ?? '')),
            'password' => (string) ($override['password'] ?? ($_ENV['DB_PASSWORD'] ?? '')),
            'charset' => (string) ($override['charset'] ?? ($_ENV['DB_CHARSET'] ?? 'utf8mb4')),
            'collation' => (string) ($override['collation'] ?? ($_ENV['DB_COLLATION'] ?? 'utf8mb4_unicode_ci')),
        ];
    }

    public static function dsn(array $config): string
    {
        return sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', (string) $config['host'], (int) $config['port'], (string) $config['name'], (string) $config['charset']);
    }
}
