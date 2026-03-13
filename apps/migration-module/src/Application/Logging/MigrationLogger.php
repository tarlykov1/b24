<?php

declare(strict_types=1);

namespace MigrationModule\Application\Logging;

use DateTimeImmutable;
use MigrationModule\Domain\Log\LogRecord;
use MigrationModule\Infrastructure\Persistence\Log\MigrationLogRepositoryInterface;

final class MigrationLogger
{
    /** @param array<int, MigrationLogRepositoryInterface> $repositories */
    public function __construct(private readonly array $repositories)
    {
    }

    public function info(string $operation, string $entityType, ?string $entityId, string $message): void
    {
        $this->write($operation, $entityType, $entityId, 'INFO', $message);
    }

    public function warning(string $operation, string $entityType, ?string $entityId, string $message): void
    {
        $this->write($operation, $entityType, $entityId, 'WARNING', $message);
    }

    public function error(string $operation, string $entityType, ?string $entityId, string $message): void
    {
        $this->write($operation, $entityType, $entityId, 'ERROR', $message);
    }

    public function log(LogRecord $record): void
    {
        foreach ($this->repositories as $repository) {
            $repository->save($record);
        }
    }

    private function write(string $operation, string $entityType, ?string $entityId, string $status, string $message): void
    {
        $this->log(new LogRecord(
            timestamp: new DateTimeImmutable(),
            operation: $operation,
            entityType: $entityType,
            entityId: $entityId,
            status: $status,
            message: $message,
        ));
    }
}
