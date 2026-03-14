<?php

declare(strict_types=1);

namespace MigrationModule\Infrastructure\Bitrix;

final class BitrixDiskAdapter
{
    public function __construct(
        private readonly BitrixRestClient $client,
        private readonly BitrixFileDownloader $downloader,
        private readonly BitrixFileUploader $uploader,
    ) {
    }

    /** @param array<string,mixed> $file */
    public function migrateFile(array $file, string $tempDir, string $targetFolderId): array
    {
        $sourceUrl = (string) ($file['DOWNLOAD_URL'] ?? $file['downloadUrl'] ?? '');
        if ($sourceUrl === '') {
            throw new \InvalidArgumentException('File does not include download URL');
        }

        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $fileId = (string) ($file['ID'] ?? $file['id'] ?? bin2hex(random_bytes(4)));
        $localPath = rtrim($tempDir, '/') . '/' . $fileId . '_' . basename((string) ($file['NAME'] ?? 'file.bin'));
        $download = $this->downloader->download($sourceUrl, $localPath, $file['CHECKSUM'] ?? null);
        $uploaded = $this->uploader->upload($targetFolderId, $localPath, $download['checksum']);

        return [
            'source_id' => $fileId,
            'target_id' => $uploaded['target_id'],
            'checksum' => $download['checksum'],
            'size' => $download['size'],
            'integrity' => $download['checksum'] === $uploaded['checksum'],
        ];
    }
}
