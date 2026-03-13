<?php

declare(strict_types=1);

namespace MigrationModule\Infrastructure\Persistence\Log;

use MigrationModule\Domain\Log\LogRecord;

interface MigrationLogRepositoryInterface
{
    public function save(LogRecord $record): void;

    /** @param array{status?:string,entity_type?:string,date_from?:string,date_to?:string} $filters
     *  @return array<int, array<string, mixed>>
     */
    public function findByFilters(array $filters): array;
}
