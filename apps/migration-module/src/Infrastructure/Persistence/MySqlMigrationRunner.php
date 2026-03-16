<?php

declare(strict_types=1);

namespace MigrationModule\Infrastructure\Persistence;

use PDO;
use RuntimeException;

final class MySqlMigrationRunner
{
    public function __construct(private readonly PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /** @return array<string,mixed> */
    public function status(): array
    {
        $this->ensureMigrationsTable();
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();

        return ['ok' => true, 'applied_migrations' => $count];
    }

    /** @return array<string,mixed> */
    public function migrate(string $schemaFile, string $lockName = 'bitrix_migration_schema_lock'): array
    {
        $this->assertMySql();
        $this->ensureMigrationsTable();

        $stmt = $this->pdo->prepare('SELECT GET_LOCK(:name, 10)');
        $stmt->execute(['name' => $lockName]);
        $locked = (int) $stmt->fetchColumn();
        if ($locked !== 1) {
            throw new RuntimeException('migration_lock_timeout');
        }

        try {
            $sql = (string) file_get_contents($schemaFile);
            if ($sql === '') {
                throw new RuntimeException('empty_schema_file');
            }

            $version = basename($schemaFile);
            $checksum = hash('sha256', $sql);
            $already = $this->pdo->prepare('SELECT checksum FROM schema_migrations WHERE version=:version LIMIT 1');
            $already->execute(['version' => $version]);
            $existingChecksum = $already->fetchColumn();
            if (is_string($existingChecksum)) {
                if ($existingChecksum !== $checksum) {
                    throw new RuntimeException('schema_checksum_mismatch_for_' . $version);
                }

                return ['ok' => true, 'status' => 'already_applied', 'version' => $version];
            }

            $this->pdo->beginTransaction();
            foreach ($this->splitStatements($sql) as $statement) {
                $trimmed = trim($statement);
                if ($trimmed === '') {
                    continue;
                }
                $this->pdo->exec($trimmed);
            }

            $ins = $this->pdo->prepare('INSERT INTO schema_migrations(version, checksum) VALUES(:version,:checksum)');
            $ins->execute(['version' => $version, 'checksum' => $checksum]);
            $this->pdo->commit();

            return ['ok' => true, 'status' => 'applied', 'version' => $version];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        } finally {
            $rel = $this->pdo->prepare('DO RELEASE_LOCK(:name)');
            $rel->execute(['name' => $lockName]);
        }
    }

    private function assertMySql(): void
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver !== 'mysql') {
            throw new RuntimeException('mysql_required_for_migrations');
        }
    }

    private function ensureMigrationsTable(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(64) PRIMARY KEY, checksum CHAR(64) NOT NULL, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
    }

    /** @return list<string> */
    private function splitStatements(string $sql): array
    {
        return array_values(array_filter(array_map('trim', explode(";\n", $sql)), static fn (string $stmt): bool => $stmt !== ''));
    }
}
