<?php

declare(strict_types=1);

namespace MigrationModule\Prototype\DB;

use MigrationModule\Prototype\Adapter\Source\MySqlSourceReadAdapter;
use MigrationModule\Prototype\Storage\MySqlStorage;

final class MySqlSourceExtractor
{
    public function __construct(
        private readonly MySqlSourceReadAdapter $adapter,
        private readonly MySqlStorage $storage,
        private readonly CursorManager $cursorManager,
        private readonly DbSafetyGuard $safetyGuard,
    ) {
    }

    public function extract(string $jobId, string $entityType, string $table, string $strategy, int $batchSize = 200): array
    {
        $this->safetyGuard->assertSafeBatch($batchSize);
        $cursor = $this->cursorManager->load($jobId, $entityType, $table);

        $whereClause = '1=1';
        $params = [];
        if ($strategy === 'auto_increment') {
            $last = (int) ($cursor['last_processed_id'] ?? 0);
            $whereClause = 'id > :last_id ORDER BY id ASC';
            $params = ['last_id' => $last];
        } elseif ($strategy === 'updated_at') {
            $lastTs = (string) ($cursor['last_processed_timestamp'] ?? '1970-01-01 00:00:00');
            $whereClause = 'updated_at > :last_ts ORDER BY updated_at ASC, id ASC';
            $params = ['last_ts' => $lastTs];
        } else {
            $lastTs = (string) ($cursor['last_processed_timestamp'] ?? '1970-01-01 00:00:00');
            $lastId = (int) ($cursor['last_processed_id'] ?? 0);
            $whereClause = '(updated_at > :last_ts OR (updated_at = :last_ts AND id > :last_id)) ORDER BY updated_at ASC, id ASC';
            $params = ['last_ts' => $lastTs, 'last_id' => $lastId];
            $strategy = 'compound';
        }

        $this->safetyGuard->assertSafeScan($whereClause, $batchSize);
        $batch = $this->adapter->fetchBatch($table, $whereClause, $params, $batchSize);
        $this->safetyGuard->throttle();

        $first = $batch[0] ?? null;
        $last = $batch[count($batch) - 1] ?? null;
        $lastId = is_array($last) ? (string) ($last['id'] ?? '') : null;
        $lastTs = is_array($last) ? (string) ($last['updated_at'] ?? $last['modified_at'] ?? '') : null;
        $this->cursorManager->save($jobId, $entityType, $table, $strategy, $lastId, $lastTs, is_array($first) ? (string) ($first['id'] ?? null) : null, $lastId);

        $boundaries = [
            'from' => is_array($first) ? (string) ($first['id'] ?? '') : null,
            'to' => $lastId,
        ];
        $this->storage->saveExtractProgress($jobId, $entityType, $table, $strategy, $batchSize, count($batch), $boundaries);

        return [
            'job_id' => $jobId,
            'entity' => $entityType,
            'table' => $table,
            'strategy' => $strategy,
            'batch_size' => $batchSize,
            'rows_read' => count($batch),
            'boundaries' => $boundaries,
            'resumable' => true,
            'records' => $batch,
        ];
    }
}
