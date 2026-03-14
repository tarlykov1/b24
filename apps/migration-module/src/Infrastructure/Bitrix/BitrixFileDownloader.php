<?php

declare(strict_types=1);

namespace MigrationModule\Infrastructure\Bitrix;

final class BitrixFileDownloader
{
    public function __construct(private readonly int $maxRetries = 3)
    {
    }

    public function download(string $url, string $targetPath, ?string $expectedChecksum = null): array
    {
        $attempt = 0;
        while (true) {
            $content = @file_get_contents($url);
            if ($content !== false) {
                file_put_contents($targetPath, $content);
                $checksum = hash_file('sha256', $targetPath) ?: '';
                if ($expectedChecksum !== null && $checksum !== $expectedChecksum) {
                    throw new \RuntimeException('Checksum mismatch for downloaded file');
                }

                return ['path' => $targetPath, 'checksum' => $checksum, 'size' => filesize($targetPath) ?: 0];
            }

            if ($attempt >= $this->maxRetries) {
                throw new \RuntimeException('Unable to download file after retries: ' . $url);
            }

            usleep((int) (150 * (2 ** $attempt)) * 1000);
            ++$attempt;
        }
    }
}
