<?php

declare(strict_types=1);

namespace MigrationModule\Application\AuditDiscovery\Inspection;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class FilesystemInspector
{
    public function inspect(string $path): array
    {
        if ($path === '' || !is_dir($path)) {
            return ['available' => false, 'reason' => 'path_unavailable'];
        }

        $totalSize = 0;
        $totalFiles = 0;
        $sizeBuckets = ['lt_10mb' => 0, 'mb_10_100' => 0, 'mb_100_1gb' => 0, 'gt_1gb' => 0];
        $mime = [];
        $hashCounts = [];
        $missingPhysicalFiles = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            ++$totalFiles;
            $size = (int) $file->getSize();
            $totalSize += $size;

            if ($size < 10 * 1024 * 1024) {
                ++$sizeBuckets['lt_10mb'];
            } elseif ($size < 100 * 1024 * 1024) {
                ++$sizeBuckets['mb_10_100'];
            } elseif ($size < 1024 * 1024 * 1024) {
                ++$sizeBuckets['mb_100_1gb'];
            } else {
                ++$sizeBuckets['gt_1gb'];
            }

            $type = (string) mime_content_type($file->getPathname());
            $mime[$type] = ($mime[$type] ?? 0) + 1;

            if (!$file->isReadable()) {
                ++$missingPhysicalFiles;
                continue;
            }

            $hash = @sha1_file($file->getPathname()) ?: '';
            if ($hash !== '') {
                $hashCounts[$hash] = ($hashCounts[$hash] ?? 0) + 1;
            }
        }

        arsort($mime);
        $duplicateFiles = array_sum(array_map(static fn (int $n): int => $n > 1 ? $n - 1 : 0, $hashCounts));

        return [
            'available' => true,
            'path' => $path,
            'total_files' => $totalFiles,
            'total_size_bytes' => $totalSize,
            'size_buckets' => $sizeBuckets,
            'mime_distribution' => array_slice($mime, 0, 20, true),
            'missing_physical_files' => $missingPhysicalFiles,
            'duplicate_files_by_checksum' => $duplicateFiles,
        ];
    }
}
