<?php

declare(strict_types=1);

namespace MigrationModule\Infrastructure\Persistence\Log;

use MigrationModule\Domain\Log\LogRecord;

final class FileMigrationLogRepository implements MigrationLogRepositoryInterface
{
    public function __construct(private readonly string $logPath)
    {
    }

    public function save(LogRecord $record): void
    {
        $directory = \dirname($this->logPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents(
            $this->logPath,
            json_encode($record->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND
        );
    }

    public function findByFilters(array $filters): array
    {
        if (!file_exists($this->logPath)) {
            return [];
        }

        $records = [];
        $lines = file($this->logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $record = json_decode($line, true);
            if (!is_array($record) || !$this->matches($record, $filters)) {
                continue;
            }

            $records[] = $record;
        }

        return $records;
    }

    /** @param array<string, mixed> $record
     *  @param array<string, string> $filters
     */
    private function matches(array $record, array $filters): bool
    {
        if (($filters['status'] ?? null) !== null && $record['status'] !== $filters['status']) {
            return false;
        }

        if (($filters['entity_type'] ?? null) !== null && $record['entity_type'] !== $filters['entity_type']) {
            return false;
        }

        if (($filters['date_from'] ?? null) !== null && $record['timestamp'] < $filters['date_from']) {
            return false;
        }

        if (($filters['date_to'] ?? null) !== null && $record['timestamp'] > $filters['date_to']) {
            return false;
        }

        return true;
    }
}
