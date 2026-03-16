<?php

declare(strict_types=1);

namespace MigrationModule\Application\Production;

use PDO;

final class UpgradeManager
{
    public function __construct(private readonly BackupManager $backupManager = new BackupManager())
    {
    }

    /** @return array<string,mixed> */
    public function check(string $root): array
    {
        $packages = glob(rtrim($root, '/') . '/upgrades/*.json') ?: [];
        sort($packages);
        return ['ok' => true, 'available' => array_map(static fn (string $p): string => basename($p), $packages)];
    }

    /** @return array<string,mixed> */
    public function install(string $root, string $package, ?PDO $pdo = null): array
    {
        $pkgPath = rtrim($root, '/') . '/upgrades/' . $package;
        if (!is_file($pkgPath)) {
            return ['ok' => false, 'error' => 'package_not_found'];
        }

        $meta = json_decode((string) file_get_contents($pkgPath), true);
        if (!is_array($meta) || !isset($meta['version'], $meta['checksum'], $meta['migrations'])) {
            return ['ok' => false, 'error' => 'invalid_package'];
        }

        $calc = hash_file('sha256', $pkgPath);
        if (!hash_equals((string) $meta['checksum'], (string) $calc)) {
            return ['ok' => false, 'error' => 'checksum_mismatch'];
        }

        $backup = $this->backupManager->create($root, 'full');
        $ran = [];
        foreach ($meta['migrations'] as $sqlFile) {
            $path = rtrim($root, '/') . '/upgrades/migrations/' . $sqlFile;
            if (!is_file($path)) {
                continue;
            }
            if ($pdo !== null) {
                $pdo->exec((string) file_get_contents($path));
            }
            $ran[] = $sqlFile;
        }

        file_put_contents(rtrim($root, '/') . '/runtime/upgrade.state.json', json_encode([
            'version' => $meta['version'],
            'installed_at' => date(DATE_ATOM),
            'backup_id' => $backup['backup_id'] ?? null,
        ], JSON_PRETTY_PRINT));

        return ['ok' => true, 'version' => $meta['version'], 'backup' => $backup, 'migrations' => $ran, 'service_restart' => 'required'];
    }

    /** @return array<string,mixed> */
    public function rollback(string $root): array
    {
        $statePath = rtrim($root, '/') . '/runtime/upgrade.state.json';
        if (!is_file($statePath)) {
            return ['ok' => false, 'error' => 'no_upgrade_state'];
        }
        $state = json_decode((string) file_get_contents($statePath), true);
        $backupId = (string) ($state['backup_id'] ?? '');
        if ($backupId === '') {
            return ['ok' => false, 'error' => 'backup_id_missing'];
        }

        $restored = $this->backupManager->restore($root, $backupId);
        return ['ok' => (bool) ($restored['ok'] ?? false), 'rollback_to_backup' => $backupId];
    }
}
