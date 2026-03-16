<?php

declare(strict_types=1);

namespace MigrationModule\Prototype\Adapter\Target;

interface MySqlTargetReadAdapter
{
    public function rowCount(string $table): int;

    /** @return array<int,array<string,mixed>> */
    public function findOrphans(string $table, string $fkColumn, string $parentTable, string $parentPk): array;
}
