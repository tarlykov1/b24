<?php

declare(strict_types=1);

namespace MigrationModule\Infrastructure\Bitrix;

final class BitrixFileUploader
{
    public function __construct(private readonly BitrixRestClient $client, private readonly int $maxRetries = 3)
    {
    }

    public function upload(string $folderId, string $localPath, ?string $expectedChecksum = null): array
    {
        $checksum = hash_file('sha256', $localPath) ?: '';
        if ($expectedChecksum !== null && $checksum !== $expectedChecksum) {
            throw new \RuntimeException('Checksum mismatch before upload');
        }

        $attempt = 0;
        while (true) {
            try {
                $response = $this->client->call('disk.folder.uploadfile', [
                    'id' => $folderId,
                    'data' => ['NAME' => basename($localPath)],
                    'fileContent' => base64_encode((string) file_get_contents($localPath)),
                    'generateUniqueName' => true,
                ]);

                return [
                    'uploaded' => true,
                    'target_id' => (string) ($response['ID'] ?? $response['id'] ?? ''),
                    'checksum' => $checksum,
                ];
            } catch (\Throwable $exception) {
                if ($attempt >= $this->maxRetries) {
                    throw $exception;
                }

                usleep((int) (200 * (2 ** $attempt)) * 1000);
                ++$attempt;
            }
        }
    }
}
