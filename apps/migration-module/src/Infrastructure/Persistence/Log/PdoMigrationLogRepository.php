<?php

declare(strict_types=1);

namespace MigrationModule\Infrastructure\Persistence\Log;

use MigrationModule\Domain\Log\LogRecord;
use PDO;

final class PdoMigrationLogRepository implements MigrationLogRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(LogRecord $record): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO migration_logs (timestamp, operation, entity_type, entity_id, status, message) VALUES (:timestamp, :operation, :entity_type, :entity_id, :status, :message)'
        );

        $statement->execute($record->toArray());
    }

    public function findByFilters(array $filters): array
    {
        $where = [];
        $params = [];

        foreach (['status', 'entity_type'] as $field) {
            if (($filters[$field] ?? null) !== null) {
                $where[] = sprintf('%s = :%s', $field, $field);
                $params[$field] = $filters[$field];
            }
        }

        if (($filters['date_from'] ?? null) !== null) {
            $where[] = 'timestamp >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (($filters['date_to'] ?? null) !== null) {
            $where[] = 'timestamp <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        $sql = 'SELECT timestamp, operation, entity_type, entity_id, status, message FROM migration_logs';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY timestamp DESC LIMIT 500';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
