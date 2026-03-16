<?php

declare(strict_types=1);

namespace MigrationModule\Preflight\Checks;

use MigrationModule\Preflight\CheckContext;
use MigrationModule\Preflight\CheckInterface;
use MigrationModule\Preflight\CheckResult;
use MigrationModule\Preflight\PreflightHelper;

final class DiskCapacityCheck implements CheckInterface
{
    public function __construct(private readonly CheckContext $context)
    {
    }

    public function run(): CheckResult
    {
        $files = PreflightHelper::recordsByAdapter($this->context->sourceAdapter, 'files', 100, 2000);
        $estimatedFileBytes = max(1, count($files)) * 250000;
        $required = (int) ($estimatedFileBytes * 1.2);

        $targetUploadPath = (string) ($this->context->configValue('target', 'upload_path', ''));
        if ($targetUploadPath === '') {
            return new CheckResult('disk_capacity', 'warning', 'Target upload path is not configured; disk capacity check skipped.', [
                'required_bytes' => $required,
            ]);
        }

        $targetFree = @disk_free_space($targetUploadPath);
        if (!is_numeric($targetFree)) {
            return new CheckResult('disk_capacity', 'warning', 'Unable to read target disk free space for ' . $targetUploadPath, [
                'required_bytes' => $required,
            ]);
        }

        $safeNeeded = (int) ceil($required * 1.5);
        $ok = (int) $targetFree >= $safeNeeded;

        return new CheckResult('disk_capacity', $ok ? 'ok' : 'blocked', $ok ? 'Target disk capacity is sufficient.' : 'Target disk free space is below required safety buffer.', [
            'target_disk_free' => (int) $targetFree,
            'required_bytes' => $required,
            'required_with_buffer' => $safeNeeded,
        ]);
    }
}
