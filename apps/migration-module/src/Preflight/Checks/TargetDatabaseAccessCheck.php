<?php

declare(strict_types=1);

namespace MigrationModule\Preflight\Checks;

use MigrationModule\Preflight\CheckContext;
use MigrationModule\Preflight\CheckInterface;
use MigrationModule\Preflight\CheckResult;
use PDO;
use Throwable;

final class TargetDatabaseAccessCheck implements CheckInterface
{
    public function __construct(private readonly CheckContext $context)
    {
    }

    public function run(): CheckResult
    {
        $dsn = (string) ($this->context->configValue('target', 'db_dsn', ''));
        if ($dsn === '') {
            return new CheckResult('target_database', 'warning', 'target.db_dsn is not configured; write-permission check skipped.');
        }

        try {
            $pdo = new PDO($dsn, (string) ($this->context->configValue('target', 'db_user', '')), (string) ($this->context->configValue('target', 'db_password', '')), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->beginTransaction();
            $pdo->exec('CREATE TEMPORARY TABLE IF NOT EXISTS preflight_write_probe (id INT)');
            $pdo->exec('INSERT INTO preflight_write_probe(id) VALUES (1)');
            $pdo->rollBack();

            return new CheckResult('target_database', 'ok', 'Target DB write transaction probe passed (temporary table + rollback).');
        } catch (Throwable $e) {
            return new CheckResult('target_database', 'blocked', 'Target DB write probe failed: ' . $e->getMessage());
        }
    }
}
