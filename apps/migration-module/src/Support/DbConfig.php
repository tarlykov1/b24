<?php

declare(strict_types=1);

namespace MigrationModule\Support;

final class DbConfig
{
    public const CANONICAL_ARTIFACT = 'config/generated-install-config.json';

    /** @param array<string,mixed> $override */
    public static function fromRuntimeSources(array $override = [], string $projectRoot = ''): array
    {
        $artifact = self::loadArtifact($projectRoot);

        $env = [
            'host' => $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: null,
            'port' => $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: null,
            'name' => $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: null,
            'user' => $_ENV['DB_USER'] ?? getenv('DB_USER') ?: null,
            'password' => $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: null,
            'charset' => $_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: null,
            'collation' => $_ENV['DB_COLLATION'] ?? getenv('DB_COLLATION') ?: null,
        ];

        $config = [
            'host' => (string) ($override['host'] ?? $env['host'] ?? $artifact['host'] ?? '127.0.0.1'),
            'port' => (int) ($override['port'] ?? $env['port'] ?? $artifact['port'] ?? 3306),
            'name' => (string) ($override['name'] ?? $env['name'] ?? $artifact['name'] ?? ''),
            'user' => (string) ($override['user'] ?? $env['user'] ?? $artifact['user'] ?? ''),
            'password' => (string) ($override['password'] ?? $env['password'] ?? $artifact['password'] ?? ''),
            'charset' => (string) ($override['charset'] ?? $env['charset'] ?? $artifact['charset'] ?? 'utf8mb4'),
            'collation' => (string) ($override['collation'] ?? $env['collation'] ?? $artifact['collation'] ?? 'utf8mb4_unicode_ci'),
        ];

        self::applyEnv($config);

        return $config;
    }

    /** @param array<string,mixed> $config */
    public static function dsn(array $config): string
    {
        return sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', (string) $config['host'], (int) $config['port'], (string) $config['name'], (string) $config['charset']);
    }

    public static function canonicalPath(string $projectRoot = ''): string
    {
        $root = $projectRoot === '' ? dirname(__DIR__, 4) : rtrim($projectRoot, '/');

        return $root . '/' . self::CANONICAL_ARTIFACT;
    }

    /** @param array<string,mixed> $config */
    public static function applyEnv(array $config): void
    {
        $_ENV['DB_HOST'] = (string) ($config['host'] ?? '127.0.0.1');
        $_ENV['DB_PORT'] = (string) ($config['port'] ?? '3306');
        $_ENV['DB_NAME'] = (string) ($config['name'] ?? '');
        $_ENV['DB_USER'] = (string) ($config['user'] ?? '');
        $_ENV['DB_PASSWORD'] = (string) ($config['password'] ?? '');
        $_ENV['DB_CHARSET'] = (string) ($config['charset'] ?? 'utf8mb4');
        $_ENV['DB_COLLATION'] = (string) ($config['collation'] ?? 'utf8mb4_unicode_ci');
    }

    /** @return array<string,mixed> */
    private static function loadArtifact(string $projectRoot = ''): array
    {
        $path = self::canonicalPath($projectRoot);
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            return [];
        }

        $mysql = $decoded['mysql'] ?? ($decoded['platform']['mysql'] ?? []);

        return is_array($mysql) ? $mysql : [];
    }
}
