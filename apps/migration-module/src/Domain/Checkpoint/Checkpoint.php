<?php

declare(strict_types=1);

namespace MigrationModule\Domain\Checkpoint;

final class Checkpoint
{
    public function __construct(
        public readonly string $scope,
        public readonly string $value,
    ) {
    }
}
