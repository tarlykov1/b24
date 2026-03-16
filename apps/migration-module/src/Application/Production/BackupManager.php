<?php

declare(strict_types=1);

namespace MigrationModule\Application\Production;

use ZipArchive;

final class BackupManager
{
    /** @return array<string,mixed> */
    public function create(string $root, string $type = 'runtime'): array
    {
        $id = date('Ymd_His') . '_' . bin2hex(random_bytes(3));
        $backupDir = rtrim($root, '/') . '/storage/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0750, true);
        }

        $archive = $backupDir . '/' . $id . '.zip';
        $zip = new ZipArchive();
        $zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $sources = [
            'runtime' => ['runtime/jobs.db', 'runtime/queue', 'runtime/logs', 'storage/snapshots'],
            'snapshot' => ['storage/snapshots'],
            'config' => ['config/migration.yaml', 'config/workers.yaml'],
            'full' => ['runtime', 'storage/snapshots', 'config'],
        ];

        foreach ($sources[$type] ?? $sources['runtime'] as $relative) {
            $this->addPath($zip, $root, $relative);
        }

        $manifest = ['id' => $id, 'type' => $type, 'created_at' => date(DATE_ATOM)];
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
        $zip->close();

        return ['ok' => true, 'backup_id' => $id, 'path' => $archive, 'type' => $type];
    }

    /** @return array<int,array<string,mixed>> */
    public function list(string $root): array
    {
        $files = glob(rtrim($root, '/') . '/storage/backups/*.zip') ?: [];
        rsort($files);
        return array_map(static fn (string $f): array => ['backup_id' => basename($f, '.zip'), 'path' => $f, 'size' => filesize($f)], $files);
    }

    /** @return array<string,mixed> */
    public function restore(string $root, string $backupId): array
    {
        $archive = rtrim($root, '/') . '/storage/backups/' . $backupId . '.zip';
        if (!is_file($archive)) {
            return ['ok' => false, 'error' => 'backup_not_found'];
        }
        $zip = new ZipArchive();
        $zip->open($archive);
        $zip->extractTo(rtrim($root, '/'));
        $zip->close();

        return ['ok' => true, 'restored_backup_id' => $backupId];
    }

    private function addPath(ZipArchive $zip, string $root, string $relative): void
    {
        $full = rtrim($root, '/') . '/' . $relative;
        if (is_file($full)) {
            $zip->addFile($full, $relative);
            return;
        }
        if (!is_dir($full)) {
            return;
        }

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($full, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $path = (string) $file->getPathname();
            $local = ltrim(str_replace(rtrim($root, '/'), '', $path), '/');
            $zip->addFile($path, $local);
        }
    }
}
