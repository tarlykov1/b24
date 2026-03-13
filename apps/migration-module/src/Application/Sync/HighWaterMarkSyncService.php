<?php

declare(strict_types=1);

namespace MigrationModule\Application\Sync;

use MigrationModule\Infrastructure\Persistence\MigrationRepository;

final class HighWaterMarkSyncService
{
    public function __construct(private readonly MigrationRepository $repository)
    {
    }

    /**
     * @param array<int,array<string,mixed>> $sourceRecords
     * @param array<int,array<string,mixed>> $targetRecords
     * @return array{new: int, changed: int, skipped: int, watermark: string|null}
     */
    public function collectDelta(string $jobId, string $entityType, array $sourceRecords, array $targetRecords): array
    {
        $watermark = $this->repository->highWaterMark($entityType);
        $targetIndex = [];
        foreach ($targetRecords as $record) {
            $targetIndex[(string) $record['id']] = $record;
        }

        $new = 0;
        $changed = 0;
        $skipped = 0;
        $nextWatermark = $watermark;

        foreach ($sourceRecords as $record) {
            $updatedAt = (string) ($record['updated_at'] ?? $record['created_at'] ?? '');
            if ($watermark !== null && $updatedAt !== '' && $updatedAt <= $watermark) {
                ++$skipped;
                continue;
            }

            $id = (string) $record['id'];
            if (!isset($targetIndex[$id])) {
                ++$new;
            } elseif ($this->signature($record) !== $this->signature($targetIndex[$id])) {
                ++$changed;
            } else {
                ++$skipped;
            }

            if ($updatedAt !== '' && ($nextWatermark === null || $updatedAt > $nextWatermark)) {
                $nextWatermark = $updatedAt;
            }
        }

        if ($nextWatermark !== null) {
            $this->repository->saveHighWaterMark($entityType, $nextWatermark);
            $this->repository->saveSyncCheckpoint($entityType, $nextWatermark);
            $this->repository->saveCheckpoint($jobId, [
                'scope' => 'high_water_mark',
                'value' => $entityType . ':' . $nextWatermark,
                'meta' => ['entity_type' => $entityType],
            ]);
        }

        return ['new' => $new, 'changed' => $changed, 'skipped' => $skipped, 'watermark' => $nextWatermark];
    }

    /** @param array<string,mixed> $record */
    private function signature(array $record): string
    {
        unset($record['updated_at'], $record['created_at']);

        return hash('sha256', json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
