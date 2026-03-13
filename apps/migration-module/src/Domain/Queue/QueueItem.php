<?php

declare(strict_types=1);

namespace MigrationModule\Domain\Queue;

final class QueueItem
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public readonly string $entityType,
        public readonly string $operation,
        public readonly string $dedupeKey,
        public readonly array $payload,
    ) {
    }
}
