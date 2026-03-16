<?php

declare(strict_types=1);

use MigrationModule\Prototype\Adapter\Source\MySqlSourceReadAdapter;
use MigrationModule\Prototype\DB\CursorManager;
use MigrationModule\Prototype\DB\DbSafetyGuard;
use MigrationModule\Prototype\DB\DbVerificationEngine;
use MigrationModule\Prototype\DB\EntityGraphBuilder;
use MigrationModule\Prototype\DB\MySqlSourceDiscovery;
use MigrationModule\Prototype\DB\MySqlSourceExtractor;
use MigrationModule\Prototype\Storage\SqliteStorage;
use PHPUnit\Framework\TestCase;

final class MySqlBackboneSmokeTest extends TestCase
{
    public function testDiscoverySnapshotAndGraphArePersisted(): void
    {
        $storagePath = __DIR__ . '/../../.prototype/mysql_backbone_discovery.sqlite';
        @unlink($storagePath);
        $storage = new SqliteStorage($storagePath);
        $storage->initSchema();
        $jobId = $storage->createJob('execute');

        $adapter = new class () implements MySqlSourceReadAdapter {
            public function fetchBatch(string $table, string $whereClause, array $params, int $limit): array { return []; }
            public function listTables(): array { return [['table_name' => 'b_user', 'table_rows' => 2, 'data_length' => 128, 'index_length' => 64], ['table_name' => 'b_tasks_task', 'table_rows' => 3, 'data_length' => 256, 'index_length' => 64]]; }
            public function columns(string $table): array { return $table === 'b_tasks_task' ? [['column_name' => 'id'], ['column_name' => 'responsible_id']] : [['column_name' => 'id']]; }
            public function indexes(string $table): array { return [['index_name' => 'PRIMARY', 'column_name' => 'id', 'non_unique' => 0]]; }
        };

        $snapshot = (new MySqlSourceDiscovery($adapter, $storage, new DbSafetyGuard()))->discover($jobId, 'mysql-assisted');
        self::assertSame('mysql-discovery-v1', $snapshot['schema_version']);
        self::assertNotEmpty($storage->latestSchemaSnapshot($jobId));

        $graph = (new EntityGraphBuilder($storage))->build($jobId, $snapshot);
        self::assertNotEmpty($graph['edges']);
        self::assertNotEmpty($storage->entityGraph($jobId));

        @unlink($storagePath);
    }

    public function testExtractorCursorResumeAndDbVerifyFoundation(): void
    {
        $storagePath = __DIR__ . '/../../.prototype/mysql_backbone_extract.sqlite';
        @unlink($storagePath);
        $storage = new SqliteStorage($storagePath);
        $storage->initSchema();
        $jobId = $storage->createJob('execute');

        $rows = [
            ['id' => '1', 'updated_at' => '2026-01-01 10:00:00', 'name' => 'A'],
            ['id' => '2', 'updated_at' => '2026-01-01 11:00:00', 'name' => 'B'],
        ];

        $adapter = new class ($rows) implements MySqlSourceReadAdapter {
            /** @param array<int,array<string,mixed>> $rows */
            public function __construct(private array $rows) {}
            public function listTables(): array { return []; }
            public function columns(string $table): array { return []; }
            public function indexes(string $table): array { return []; }
            public function fetchBatch(string $table, string $whereClause, array $params, int $limit): array
            {
                $lastId = (int) ($params['last_id'] ?? 0);
                $filtered = array_values(array_filter($this->rows, static fn (array $row): bool => (int) $row['id'] > $lastId));

                return array_slice($filtered, 0, $limit);
            }
        };

        $extractor = new MySqlSourceExtractor($adapter, $storage, new CursorManager($storage), new DbSafetyGuard(1000, 2, 0));
        $first = $extractor->extract($jobId, 'users', 'b_user', 'auto_increment', 1);
        self::assertSame(1, $first['rows_read']);
        $cursor = $storage->cursor($jobId, 'users', 'b_user');
        self::assertSame('1', (string) ($cursor['last_processed_id'] ?? ''));

        $second = $extractor->extract($jobId, 'users', 'b_user', 'auto_increment', 1);
        self::assertSame(1, $second['rows_read']);
        $cursorAfter = $storage->cursor($jobId, 'users', 'b_user');
        self::assertSame('2', (string) ($cursorAfter['last_processed_id'] ?? ''));

        $storage->saveSchemaSnapshot($jobId, 'mysql-discovery-v1', 'hybrid', ['tables' => ['b_user' => ['row_count' => 2]], 'detected_relations' => []]);
        $storage->saveEntityGraph($jobId, ['nodes' => ['b_user'], 'edges' => []]);
        $verify = (new DbVerificationEngine($storage))->verify($jobId, 'source_db');
        self::assertTrue($verify['verified_via_source_db']);
        self::assertSame('db_truth_foundation', $verify['verify_depth']);

        @unlink($storagePath);
    }
}
