<?php

declare(strict_types=1);

namespace MigrationModule\Prototype\DB;

use MigrationModule\Prototype\Adapter\Source\MySqlSourceReadAdapter;
use MigrationModule\Prototype\Storage\SqliteStorage;

final class MySqlSourceDiscovery
{
    public function __construct(
        private readonly MySqlSourceReadAdapter $adapter,
        private readonly SqliteStorage $storage,
        private readonly DbSafetyGuard $safetyGuard,
    ) {
    }

    public function discover(string $jobId, string $runtimeMode = 'mysql-assisted'): array
    {
        $tables = $this->adapter->listTables();
        $tableMeta = [];
        $relations = [];
        $classifications = [];
        $customFields = [];

        foreach ($tables as $table) {
            $tableName = (string) ($table['table_name'] ?? '');
            if ($tableName === '') {
                continue;
            }

            $columns = $this->adapter->columns($tableName);
            $indexes = $this->adapter->indexes($tableName);
            $tableMeta[$tableName] = [
                'columns' => $columns,
                'indexes' => $indexes,
                'row_count' => (int) ($table['table_rows'] ?? 0),
                'estimated_size' => (int) (($table['data_length'] ?? 0) + ($table['index_length'] ?? 0)),
            ];

            $classifications[$tableName] = $this->classifyTable($tableName);
            if ($this->isCustomFieldTable($tableName)) {
                $customFields[] = $tableName;
            }

            foreach ($columns as $column) {
                $name = (string) ($column['column_name'] ?? '');
                if (str_ends_with($name, '_id') && $name !== 'id') {
                    $parent = substr($name, 0, -3);
                    $relations[] = ['from_table' => $tableName, 'column' => $name, 'to_entity_hint' => $parent];
                }
            }
        }

        $snapshot = [
            'schema_version' => 'mysql-discovery-v1',
            'runtime_mode' => $runtimeMode,
            'tables' => $tableMeta,
            'detected_relations' => $relations,
            'entity_classifications' => $classifications,
            'custom_fields' => $customFields,
        ];

        $this->storage->saveSchemaSnapshot($jobId, (string) $snapshot['schema_version'], $runtimeMode, $snapshot);

        return $snapshot;
    }

    private function classifyTable(string $table): string
    {
        $map = [
            'user' => 'users',
            'department' => 'departments',
            'group' => 'groups',
            'task' => 'tasks',
            'crm' => 'crm',
            'smart' => 'smart_processes',
            'file' => 'files',
            'disk' => 'disk_objects',
            'comment' => 'comments',
            'bind' => 'pivots',
        ];

        $lower = strtolower($table);
        foreach ($map as $needle => $entity) {
            if (str_contains($lower, $needle)) {
                return $entity;
            }
        }

        return 'unclassified';
    }

    private function isCustomFieldTable(string $table): bool
    {
        $lower = strtolower($table);

        return str_contains($lower, 'utm_') || str_contains($lower, 'uts_') || str_contains($lower, 'user_field');
    }
}
