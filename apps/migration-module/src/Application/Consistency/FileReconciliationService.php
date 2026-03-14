<?php

declare(strict_types=1);

namespace MigrationModule\Application\Consistency;

final class FileReconciliationService
{
    /** @param array<int,array<string,mixed>> $files */
    public function verify(array $files): array
    {
        $verified = 0;
        $orphans = 0;
        $checksumMismatch = 0;

        foreach ($files as $file) {
            $bound = (bool) ($file['parent_bound'] ?? false);
            $checksumOk = ($file['source_checksum'] ?? null) === ($file['target_checksum'] ?? null);

            if (!$bound) {
                $orphans++;
            }
            if (!$checksumOk) {
                $checksumMismatch++;
            }
            if ($bound && $checksumOk) {
                $verified++;
            }
        }

        return [
            'total' => count($files),
            'verified' => $verified,
            'orphan_file_repair_needed' => $orphans,
            'checksum_mismatch' => $checksumMismatch,
            'healthy' => $orphans === 0 && $checksumMismatch === 0,
        ];
    }
}
