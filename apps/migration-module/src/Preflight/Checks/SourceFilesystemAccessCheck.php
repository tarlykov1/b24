<?php

declare(strict_types=1);

namespace MigrationModule\Preflight\Checks;

use FilesystemIterator;
use MigrationModule\Preflight\CheckContext;
use MigrationModule\Preflight\CheckInterface;
use MigrationModule\Preflight\CheckResult;

final class SourceFilesystemAccessCheck implements CheckInterface
{
    public function __construct(private readonly CheckContext $context)
    {
    }

    public function run(): CheckResult
    {
        $uploadPath = (string) ($this->context->configValue('source', 'upload_path', ($_ENV['BITRIX_UPLOAD_PATH'] ?? '')));
        if ($uploadPath === '') {
            return new CheckResult('source_filesystem', 'warning', 'Source upload path is not configured; filesystem checks skipped.');
        }
        if (!is_dir($uploadPath) || !is_readable($uploadPath)) {
            return new CheckResult('source_filesystem', 'blocked', 'Source upload path is not readable: ' . $uploadPath);
        }

        $totalFiles = 0;
        $totalSize = 0;
        $largeFiles = 0;
        $brokenPaths = 0;
        $iterator = new FilesystemIterator($uploadPath, FilesystemIterator::SKIP_DOTS);
        foreach ($iterator as $entry) {
            if (!$entry->isFile()) {
                continue;
            }
            $totalFiles++;
            $size = (int) $entry->getSize();
            $totalSize += $size;
            if ($size > 1024 * 1024 * 1024) {
                $largeFiles++;
            }
            if (!file_exists($entry->getPathname())) {
                $brokenPaths++;
            }
            if ($totalFiles >= 1000) {
                break;
            }
        }

        $usage = @disk_total_space($uploadPath);
        $free = @disk_free_space($uploadPath);

        $status = ($brokenPaths > 0) ? 'warning' : 'ok';
        $details = $brokenPaths > 0 ? 'Broken file path references detected in sample.' : 'Filesystem read checks passed.';

        return new CheckResult('source_filesystem', $status, $details, [
            'total_files' => $totalFiles,
            'total_size' => $totalSize,
            'sampled_only' => true,
            'disk_total' => is_numeric($usage) ? (int) $usage : null,
            'disk_free' => is_numeric($free) ? (int) $free : null,
            'risk_huge_files' => $largeFiles,
            'risk_broken_paths' => $brokenPaths,
        ]);
    }
}
