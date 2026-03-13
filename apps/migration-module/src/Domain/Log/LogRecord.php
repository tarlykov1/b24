<?php

declare(strict_types=1);

namespace MigrationModule\Domain\Log;

final class LogRecord
{
    public function __construct(
        public readonly string $level,
        public readonly string $entityType,
        public readonly ?string $oldId,
        public readonly ?string $newId,
        public readonly string $action,
        public readonly ?string $error,
        public readonly int $retryCount,
        public readonly int $durationMs,
    ) {
    }
}
