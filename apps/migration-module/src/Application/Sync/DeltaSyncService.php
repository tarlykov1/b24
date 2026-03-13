<?php

declare(strict_types=1);

namespace MigrationModule\Application\Sync;

use MigrationModule\Infrastructure\Persistence\MigrationRepository;

final class DeltaSyncService
{
    public function __construct(private readonly MigrationRepository $repository)
    {
    }

    /**
     * @param array<int,array<string,mixed>> $sourceRecords
     * @param array<int,array<string,mixed>> $targetRecords
     * @return array<string,mixed>
     */
    public function detectDelta(string $jobId, string $entityType, array $sourceRecords, array $targetRecords, ?string $lastSyncAt): array
    {
        $targetById = [];
        foreach ($targetRecords as $targetRecord) {
            $targetById[(string) $targetRecord['id']] = $targetRecord;
        }

        $new = 0;
        $changed = 0;
        $conflicts = 0;
        $items = [];

        foreach ($sourceRecords as $sourceRecord) {
            $sourceId = (string) $sourceRecord['id'];
            $mappedId = $this->repository->findMappedId($jobId, $entityType, $sourceId) ?? $sourceId;
            $targetRecord = $targetById[$mappedId] ?? null;
            $updatedAt = (string) ($sourceRecord['updated_at'] ?? $sourceRecord['modified_at'] ?? '');

            if ($lastSyncAt !== null && $updatedAt !== '' && strtotime($updatedAt) <= strtotime($lastSyncAt)) {
                continue;
            }

            if ($targetRecord === null) {
                $new++;
                $items[] = ['id' => $sourceId, 'action' => 'create'];
                continue;
            }

            if ($this->payloadHash($sourceRecord) !== $this->payloadHash($targetRecord)) {
                $changed++;
                $action = ((string) ($targetRecord['updated_at'] ?? '')) > $lastSyncAt ? 'conflict' : 'update';
                if ($action === 'conflict') {
                    $conflicts++;
                }
                $items[] = ['id' => $sourceId, 'action' => $action];
            }
        }

        return [
            'new' => $new,
            'changed' => $changed,
            'conflicts' => $conflicts,
            'continue_allowed' => $conflicts === 0,
            'items' => $items,
        ];
    }

    /** @param array<string,mixed> $record */
    private function payloadHash(array $record): string
    {
        unset($record['updated_at'], $record['modified_at']);

        return hash('sha256', json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
