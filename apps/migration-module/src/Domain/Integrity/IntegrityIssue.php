<?php

declare(strict_types=1);

namespace MigrationModule\Domain\Integrity;

use DateTimeImmutable;

final class IntegrityIssue
{
    public function __construct(
        public readonly string $entityType,
        public readonly string $entityId,
        public readonly string $problemType,
        public readonly string $description,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'problem_type' => $this->problemType,
            'description' => $this->description,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
