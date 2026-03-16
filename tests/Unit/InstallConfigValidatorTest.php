<?php

declare(strict_types=1);

use MigrationModule\Installation\InstallConfigValidator;
use PHPUnit\Framework\TestCase;

final class InstallConfigValidatorTest extends TestCase
{
    public function testBlocksPlatformSchemaOverlapAndIdenticalSourceTarget(): void
    {
        $validator = new InstallConfigValidator();
        $result = $validator->validate([
            'platform' => [
                'mysql_dsn' => 'mysql:host=db1;dbname=bitrix_prod;charset=utf8mb4',
                'install_dir' => '/opt/bitrix-migration',
                'log_dir' => '/var/log/bitrix-migration',
                'temp_dir' => '/var/lib/bitrix-migration/tmp',
            ],
            'source' => ['db_dsn' => 'mysql:host=db1;dbname=bitrix_prod;charset=utf8mb4'],
            'target' => ['db_dsn' => 'mysql:host=db1;dbname=bitrix_prod;charset=utf8mb4'],
            'execution' => ['workers' => 2, 'batch_size' => 100],
        ]);

        self::assertContains('source_target_identical_detected', $result['blockers']);
        self::assertContains('platform_schema_overlaps_bitrix_operational_schema', $result['blockers']);
    }

    public function testWarnsOnAggressiveSettings(): void
    {
        $validator = new InstallConfigValidator();
        $result = $validator->validate([
            'platform' => [
                'mysql_dsn' => 'mysql:host=platform-db;dbname=bitrix_migration;charset=utf8mb4',
                'install_dir' => '/opt/bitrix-migration',
                'log_dir' => '/var/log/bitrix-migration',
                'temp_dir' => '/var/lib/bitrix-migration/tmp',
            ],
            'source' => ['db_dsn' => 'mysql:host=source-db;dbname=bitrix_source;charset=utf8mb4'],
            'target' => ['db_dsn' => 'mysql:host=target-db;dbname=bitrix_target;charset=utf8mb4', 'write_enabled' => true],
            'execution' => ['workers' => 16, 'batch_size' => 1000],
        ]);

        self::assertContains('aggressive_runtime_limits_selected', $result['warnings']);
        self::assertContains('target_write_mode_enabled_requires_explicit_ack', $result['warnings']);
    }
}
