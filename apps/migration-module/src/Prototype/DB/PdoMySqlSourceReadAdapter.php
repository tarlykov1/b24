<?php

declare(strict_types=1);

namespace MigrationModule\Prototype\DB;

use MigrationModule\Prototype\Adapter\Source\MySqlSourceReadAdapter;
use PDO;

final class PdoMySqlSourceReadAdapter implements MySqlSourceReadAdapter
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function fetchBatch(string $table, string $whereClause, array $params, int $limit): array
    {
        $safeLimit = max(1, $limit);
        $sql = 'SELECT * FROM `' . str_replace('`', '', $table) . '`';
        if (trim($whereClause) !== '') {
            $sql .= ' WHERE ' . $whereClause;
        }
        $sql .= ' LIMIT ' . $safeLimit;

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(is_int($key) ? $key + 1 : (string) $key, $value);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listTables(): array
    {
        $stmt = $this->pdo->query('SELECT table_name, table_rows, data_length, index_length FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY table_name');

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function columns(string $table): array
    {
        $stmt = $this->pdo->prepare('SELECT column_name, data_type, is_nullable, column_key, extra FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table ORDER BY ordinal_position');
        $stmt->execute(['table' => $table]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function indexes(string $table): array
    {
        $stmt = $this->pdo->prepare('SELECT index_name, column_name, non_unique FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = :table ORDER BY index_name, seq_in_index');
        $stmt->execute(['table' => $table]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
