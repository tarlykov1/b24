<?php

declare(strict_types=1);

namespace MigrationModule\Prototype;

final class ConfigLoader
{
    /** @return array<string,mixed> */
    public function load(string $path): array
    {
        if (!is_file($path)) {
            throw new \RuntimeException("Config not found: {$path}");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $config = [];
        $currentSection = null;

        foreach ($lines as $line) {
            $line = rtrim($line);
            if ($line === '' || str_starts_with(trim($line), '#')) {
                continue;
            }

            if (preg_match('/^([a-zA-Z0-9_]+):\s*$/', $line, $m) === 1) {
                $currentSection = $m[1];
                $config[$currentSection] = [];
                continue;
            }

            if (preg_match('/^\s{2}([a-zA-Z0-9_]+):\s*(.+)$/', $line, $m) === 1 && $currentSection !== null) {
                $config[$currentSection][$m[1]] = $this->cast($m[2]);
                continue;
            }

            if (preg_match('/^([a-zA-Z0-9_]+):\s*(.+)$/', $line, $m) === 1) {
                $currentSection = null;
                $config[$m[1]] = $this->cast($m[2]);
            }
        }

        $config['storage'] = $config['storage'] ?? [
            'driver' => 'mysql',
            'host' => (string) ($_ENV['DB_HOST'] ?? '127.0.0.1'),
            'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
            'name' => (string) ($_ENV['DB_NAME'] ?? ''),
            'user' => (string) ($_ENV['DB_USER'] ?? ''),
            'password' => (string) ($_ENV['DB_PASSWORD'] ?? ''),
            'charset' => (string) ($_ENV['DB_CHARSET'] ?? 'utf8mb4'),
            'collation' => (string) ($_ENV['DB_COLLATION'] ?? 'utf8mb4_unicode_ci'),
        ];
        $config['user_policy'] = $config['user_policy'] ?? [
            'cutoff_date' => '2024-01-01T00:00:00+00:00',
            'inactive_strategy' => 'preserve_without_activation',
            'system_user_id' => 'system',
        ];

        return $config;
    }

    private function cast(string $value): mixed
    {
        $value = trim($value, " \t\n\r\0\x0B\"");
        return match (true) {
            $value === 'true' => true,
            $value === 'false' => false,
            is_numeric($value) && str_contains($value, '.') => (float) $value,
            is_numeric($value) => (int) $value,
            default => $value,
        };
    }
}
