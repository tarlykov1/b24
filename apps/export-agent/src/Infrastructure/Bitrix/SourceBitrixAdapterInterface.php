<?php

declare(strict_types=1);

namespace ExportAgent\Infrastructure\Bitrix;

interface SourceBitrixAdapterInterface
{
    /**
     * @return array{items: array<int, array<string, mixed>>, next_cursor: ?string}
     */
    public function fetchBatch(string $entityType, ?string $cursor, int $batchSize): array;
}
