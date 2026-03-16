<?php

declare(strict_types=1);

namespace MigrationModule\Application\Execution\Deterministic;

use MigrationModule\Prototype\Storage\SqliteStorage;

final class FilesystemTransferEngine
{
    public function __construct(private readonly SqliteStorage $storage)
    {
    }

    public function transfer(string $planId, string $sourcePath, string $targetPath, ?string $relationKey = null): array
    {
        $ok = is_file($sourcePath);
        if ($ok) {
            @mkdir(dirname($targetPath), 0777, true);
            copy($sourcePath, $targetPath);
        }

        $sourceSize = $ok ? filesize($sourcePath) ?: 0 : 0;
        $targetSize = is_file($targetPath) ? (filesize($targetPath) ?: 0) : 0;
        $sourceChecksum = $ok ? sha1_file($sourcePath) : null;
        $targetChecksum = is_file($targetPath) ? sha1_file($targetPath) : null;
        $status = ($ok && $sourceSize === $targetSize && $sourceChecksum === $targetChecksum) ? 'verified' : 'failed';

        $this->storage->saveFileTransferMap([
            'plan_id' => $planId,
            'source_file_id' => null,
            'source_path' => $sourcePath,
            'source_checksum' => $sourceChecksum,
            'source_size' => $sourceSize,
            'target_file_id' => null,
            'target_path' => $targetPath,
            'target_checksum' => $targetChecksum,
            'target_size' => $targetSize,
            'relation_key' => $relationKey,
            'status' => $status,
            'resume_token' => null,
        ]);

        return ['status' => $status, 'source_path' => $sourcePath, 'target_path' => $targetPath];
    }
}
