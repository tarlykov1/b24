<?php

declare(strict_types=1);

namespace MigrationModule\Domain\Log;

use DateTimeImmutable;

final class LogRecord
{
    public function __construct(
        public readonly DateTimeImmutable $timestamp,
        public readonly string $operation,
        public readonly string $entityType,
        public readonly ?string $entityId,
        public readonly string $status,
        public readonly string $message,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'timestamp' => $this->timestamp->format(DateTimeImmutable::ATOM),
            'operation' => $this->operation,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'status' => $this->status,
            'message' => $this->message,
        ];
    }
}
