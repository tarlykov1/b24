<?php

declare(strict_types=1);

namespace MigrationModule\Preflight\Checks;

use MigrationModule\Preflight\CheckContext;
use MigrationModule\Preflight\CheckInterface;
use MigrationModule\Preflight\CheckResult;
use PDO;
use Throwable;

final class SourceDatabaseConnectivityCheck implements CheckInterface
{
    public function __construct(private readonly CheckContext $context)
    {
    }

    public function run(): CheckResult
    {
        $dsn = (string) ($this->context->configValue('source', 'db_dsn', ''));
        if ($dsn === '') {
            return new CheckResult('source_database', 'warning', 'source.db_dsn is not configured; DB checks skipped.');
        }

        try {
            $pdo = new PDO($dsn, (string) ($this->context->configValue('source', 'db_user', '')), (string) ($this->context->configValue('source', 'db_password', '')), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->query('SELECT 1')->fetchColumn();
            $missing = [];
            foreach (['b_user', 'b_tasks', 'b_crm_deal', 'b_file'] as $table) {
                try {
                    $pdo->query('SELECT 1 FROM ' . $table . ' LIMIT 1')->fetchColumn();
                } catch (Throwable) {
                    $missing[] = $table;
                }
            }
            if ($missing !== []) {
                return new CheckResult('source_database', 'blocked', 'Required source tables are missing.', ['missing_tables' => $missing]);
            }

            return new CheckResult('source_database', 'ok', 'Source DB connectivity and schema checks passed.');
        } catch (Throwable $e) {
            return new CheckResult('source_database', 'blocked', 'Source DB unreachable: ' . $e->getMessage());
        }
    }
}
