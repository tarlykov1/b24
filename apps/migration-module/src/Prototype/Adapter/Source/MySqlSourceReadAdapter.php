<?php

declare(strict_types=1);

namespace MigrationModule\Prototype\Adapter\Source;

interface MySqlSourceReadAdapter
{
    /** @return array<int,array<string,mixed>> */
    public function fetchBatch(string $table, string $whereClause, array $params, int $limit): array;

    /** @return array<int,array<string,mixed>> */
    public function listTables(): array;

    /** @return array<int,array<string,mixed>> */
    public function columns(string $table): array;

    /** @return array<int,array<string,mixed>> */
    public function indexes(string $table): array;
}
