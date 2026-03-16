<?php

declare(strict_types=1);

namespace MigrationModule\Application\Production;

final class LogRotationService
{
    /** @return array<string,mixed> */
    public function rotate(string $logDir, int $maxBytes = 5242880, int $retention = 10): array
    {
        if (!is_dir($logDir)) {
            return ['ok' => true, 'rotated' => [], 'deleted' => []];
        }

        $rotated = [];
        foreach (glob(rtrim($logDir, '/') . '/*.log') ?: [] as $file) {
            if (filesize($file) < $maxBytes) {
                continue;
            }
            $archive = $file . '.' . date('YmdHis') . '.gz';
            $data = file_get_contents($file);
            if ($data !== false) {
                file_put_contents($archive, gzencode($data, 9));
                file_put_contents($file, '');
                $rotated[] = basename($archive);
            }
        }

        $archives = glob(rtrim($logDir, '/') . '/*.gz') ?: [];
        rsort($archives);
        $deleted = [];
        foreach (array_slice($archives, $retention) as $old) {
            @unlink($old);
            $deleted[] = basename($old);
        }

        return ['ok' => true, 'rotated' => $rotated, 'deleted' => $deleted];
    }
}
