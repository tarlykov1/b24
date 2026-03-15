<?php

declare(strict_types=1);

namespace MigrationModule\Application\Hypercare;

final class ArchiveManager
{
    /** @param array<string,mixed> $artifacts */
    public function archive(array $artifacts, string $dir): array
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $index = $dir . '/migration-archive-index.json';
        $payload = [
            'created_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'artifacts' => $artifacts,
            'purpose' => ['compliance', 'future_audits', 're_migrations'],
        ];
        file_put_contents($index, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return ['index' => $index, 'artifact_count' => count($artifacts)];
    }
}
