<?php

declare(strict_types=1);

namespace MigrationModule\Installation;

final class InstallConfigValidator
{
    /** @param array<string,mixed> $config
     * @return array{blockers: list<string>, warnings: list<string>, recommendations: list<string>}
     */
    public function validate(array $config): array
    {
        $blockers = [];
        $warnings = [];
        $recommendations = [];

        $platform = $this->parseDsn((string) ($config['platform']['mysql_dsn'] ?? ''));
        $source = $this->parseDsn((string) ($config['source']['db_dsn'] ?? ''));
        $target = $this->parseDsn((string) ($config['target']['db_dsn'] ?? ''));

        if ($platform['db'] === '' || $platform['host'] === '') {
            $blockers[] = 'platform_mysql_dsn_required';
        }

        if ($source['db'] !== '' && $target['db'] !== '' && $source['host'] === $target['host'] && $source['db'] === $target['db']) {
            $blockers[] = 'source_target_identical_detected';
        }

        if ($platform['db'] !== '' && (($platform['host'] === $source['host'] && $platform['db'] === $source['db']) || ($platform['host'] === $target['host'] && $platform['db'] === $target['db']))) {
            $blockers[] = 'platform_schema_overlaps_bitrix_operational_schema';
        }

        foreach (['install_dir', 'log_dir', 'temp_dir'] as $dirKey) {
            $path = (string) ($config['platform'][$dirKey] ?? '');
            if ($path === '') {
                $blockers[] = $dirKey . '_required';
                continue;
            }

            if (str_starts_with($path, '/bitrix') || str_contains($path, '/bitrix/modules') || str_contains($path, '/bitrix/php_interface')) {
                $blockers[] = $dirKey . '_unsafe_path';
            }

            if (in_array($path, ['/', '/etc', '/var', '/usr', '/root'], true)) {
                $blockers[] = $dirKey . '_risky_root_path';
            }
        }

        $temp = (string) ($config['platform']['temp_dir'] ?? '');
        $install = (string) ($config['platform']['install_dir'] ?? '');
        if ($temp !== '' && $install !== '' && str_starts_with($temp, $install . '/')) {
            $warnings[] = 'temp_dir_nested_inside_install_dir';
        }

        $workers = (int) ($config['execution']['workers'] ?? 2);
        $batch = (int) ($config['execution']['batch_size'] ?? 100);
        if ($workers > 8 || $batch > 500) {
            $warnings[] = 'aggressive_runtime_limits_selected';
            $recommendations[] = 'use_conservative_profile_workers<=4_batch<=200';
        }

        if (($config['target']['write_enabled'] ?? false) === true) {
            $warnings[] = 'target_write_mode_enabled_requires_explicit_ack';
        }

        return ['blockers' => $blockers, 'warnings' => $warnings, 'recommendations' => $recommendations];
    }

    /** @return array{host: string, db: string} */
    private function parseDsn(string $dsn): array
    {
        if (!str_starts_with($dsn, 'mysql:')) {
            return ['host' => '', 'db' => ''];
        }

        $str = substr($dsn, 6);
        $parts = explode(';', $str);
        $values = ['host' => '', 'dbname' => ''];
        foreach ($parts as $part) {
            if (!str_contains($part, '=')) {
                continue;
            }
            [$k, $v] = array_map('trim', explode('=', $part, 2));
            if ($k === 'host' || $k === 'dbname') {
                $values[$k] = $v;
            }
        }

        return ['host' => strtolower($values['host']), 'db' => strtolower($values['dbname'])];
    }
}
