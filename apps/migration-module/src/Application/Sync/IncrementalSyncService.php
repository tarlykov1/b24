<?php

declare(strict_types=1);

namespace MigrationModule\Application\Sync;

use DateTimeImmutable;
use MigrationModule\Application\Logging\MigrationLogger;
use MigrationModule\Domain\Sync\SyncMode;
use MigrationModule\Infrastructure\Persistence\MigrationStateRepository;

final class IncrementalSyncService
{
    public function __construct(
        private readonly MigrationStateRepository $stateRepository,
        private readonly MigrationLogger $logger,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return array<int, array<string, mixed>>
     */
    public function selectRecordsToSync(string $entityType, array $records, string $mode): array
    {
        if ($mode === SyncMode::FULL_MIGRATION) {
            $this->logger->info('sync_mode', $entityType, null, 'FULL_MIGRATION selected');
            return $records;
        }

        $state = $this->stateRepository->findByEntityType($entityType);
        $lastSyncTime = $state['last_sync_time'] ?? null;

        $filtered = array_values(array_filter($records, static function (array $record) use ($lastSyncTime): bool {
            $updatedAt = $record['DATE_MODIFY'] ?? $record['UPDATED_AT'] ?? null;
            if ($updatedAt === null || $lastSyncTime === null) {
                return true;
            }

            return strtotime((string) $updatedAt) > strtotime((string) $lastSyncTime);
        }));

        $this->logger->info('sync_mode', $entityType, null, sprintf('INCREMENTAL_SYNC selected, %d records for sync', count($filtered)));

        return $filtered;
    }

    public function markCompleted(string $entityType, string $lastProcessedId, int $recordsProcessed): void
    {
        $this->stateRepository->upsert(
            entityType: $entityType,
            lastProcessedId: $lastProcessedId,
            lastSyncTime: (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            recordsProcessed: $recordsProcessed,
            status: 'completed',
        );
    }
}
