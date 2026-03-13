<?php

declare(strict_types=1);

namespace MigrationModule\Domain\Mapping;

final class MappingResult
{
    public function __construct(
        public readonly int|string $sourceId,
        public readonly int|string $targetId,
        public readonly bool $preservedId,
        public readonly ?string $remapReason,
    ) {
    }
}
