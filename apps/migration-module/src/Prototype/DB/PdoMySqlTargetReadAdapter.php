<?php

declare(strict_types=1);

namespace MigrationModule\Prototype\DB;

use MigrationModule\Prototype\Adapter\Target\MySqlTargetReadAdapter;
use PDO;

final class PdoMySqlTargetReadAdapter implements MySqlTargetReadAdapter
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function rowCount(string $table): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM `' . str_replace('`', '', $table) . '`');

        return (int) $stmt->fetchColumn();
    }

    public function findOrphans(string $table, string $fkColumn, string $parentTable, string $parentPk): array
    {
        $sql = sprintf(
            'SELECT c.* FROM `%s` c LEFT JOIN `%s` p ON c.`%s` = p.`%s` WHERE c.`%s` IS NOT NULL AND p.`%s` IS NULL LIMIT 500',
            str_replace('`', '', $table),
            str_replace('`', '', $parentTable),
            str_replace('`', '', $fkColumn),
            str_replace('`', '', $parentPk),
            str_replace('`', '', $fkColumn),
            str_replace('`', '', $parentPk),
        );

        $stmt = $this->pdo->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
