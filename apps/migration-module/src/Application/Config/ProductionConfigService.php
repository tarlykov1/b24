<?php

declare(strict_types=1);

namespace MigrationModule\Application\Config;

use RuntimeException;

final class ProductionConfigService
{
    /** @var array<string,array<string,mixed>> */
    private const PROFILES = [
        'safe' => [
            'rate_limit' => 30,
            'batch_size' => 50,
            'retry_policy' => ['max_retries' => 5, 'base_delay_ms' => 250, 'max_delay_ms' => 5000],
            'api_timeout' => 20,
            'parallel_workers' => 1,
            'delta_sync_interval' => 60,
            'freeze_mode_enabled' => true,
            'conflict_strategy' => 'manual',
            'logging_level' => 'debug',
        ],
        'balanced' => [
            'rate_limit' => 80,
            'batch_size' => 100,
            'retry_policy' => ['max_retries' => 4, 'base_delay_ms' => 150, 'max_delay_ms' => 4000],
            'api_timeout' => 15,
            'parallel_workers' => 3,
            'delta_sync_interval' => 30,
            'freeze_mode_enabled' => true,
            'conflict_strategy' => 'prefer_target',
            'logging_level' => 'info',
        ],
        'aggressive' => [
            'rate_limit' => 150,
            'batch_size' => 250,
            'retry_policy' => ['max_retries' => 3, 'base_delay_ms' => 100, 'max_delay_ms' => 2500],
            'api_timeout' => 10,
            'parallel_workers' => 6,
            'delta_sync_interval' => 15,
            'freeze_mode_enabled' => false,
            'conflict_strategy' => 'prefer_source',
            'logging_level' => 'warning',
        ],
    ];

    /** @return array<string,mixed> */
    public function load(string $configPath = 'migration.config.yml'): array
    {
        if (!is_file($configPath)) {
            return ['profile' => 'balanced'] + self::PROFILES['balanced'];
        }

        $raw = $this->parseSimpleYaml((string) file_get_contents($configPath));
        $profile = (string) ($raw['profile'] ?? 'balanced');
        if (!isset(self::PROFILES[$profile])) {
            throw new RuntimeException(sprintf('Unknown profile: %s', $profile));
        }

        return array_replace_recursive(['profile' => $profile] + self::PROFILES[$profile], $raw);
    }

    /** @return array<string,mixed> */
    private function parseSimpleYaml(string $content): array
    {
        $result = [];
        $currentSection = null;

        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (!str_contains($line, ':')) {
                continue;
            }

            if (preg_match('/^([a-zA-Z0-9_]+):\s*$/', $trimmed, $matches) === 1) {
                $currentSection = $matches[1];
                $result[$currentSection] = [];
                continue;
            }

            [$key, $value] = array_map('trim', explode(':', $trimmed, 2));
            $normalized = $this->normalizeValue($value);

            if (str_starts_with($line, '  ') && $currentSection !== null && is_array($result[$currentSection])) {
                $result[$currentSection][$key] = $normalized;
                continue;
            }

            $currentSection = null;
            $result[$key] = $normalized;
        }

        return $result;
    }

    private function normalizeValue(string $value): mixed
    {
        $value = trim($value, " \t\n\r\0\x0B\"");
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return match (strtolower($value)) {
            'true' => true,
            'false' => false,
            default => $value,
        };
    }
}
