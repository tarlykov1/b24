<?php

declare(strict_types=1);

namespace ExportAgent\Domain;

final class ExportBatch
{
    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function __construct(
        public readonly string $entityType,
        public readonly array $items,
        public readonly ?string $nextCursor,
    ) {
    }
}
