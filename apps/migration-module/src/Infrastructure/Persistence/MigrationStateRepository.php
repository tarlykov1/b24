<?php

declare(strict_types=1);

namespace MigrationModule\Infrastructure\Persistence;

use PDO;

final class MigrationStateRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed>|null */
    public function findByEntityType(string $entityType): ?array
    {
        $stmt = $this->pdo->prepare('SELECT entity_type, last_processed_id, last_sync_time, records_processed, status FROM migration_state WHERE entity_type = :entity_type LIMIT 1');
        $stmt->execute(['entity_type' => $entityType]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function upsert(string $entityType, string $lastProcessedId, string $lastSyncTime, int $recordsProcessed, string $status): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO migration_state (entity_type, last_processed_id, last_sync_time, records_processed, status) VALUES (:entity_type, :last_processed_id, :last_sync_time, :records_processed, :status)
             ON DUPLICATE KEY UPDATE last_processed_id = VALUES(last_processed_id), last_sync_time = VALUES(last_sync_time), records_processed = VALUES(records_processed), status = VALUES(status), updated_at = CURRENT_TIMESTAMP'
        );

        $stmt->execute([
            'entity_type' => $entityType,
            'last_processed_id' => $lastProcessedId,
            'last_sync_time' => $lastSyncTime,
            'records_processed' => $recordsProcessed,
            'status' => $status,
        ]);
    }
}
