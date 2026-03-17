<?php

declare(strict_types=1);

use MigrationModule\Support\DbConfig;
use PHPUnit\Framework\TestCase;

final class DbConfigRuntimeSourcesTest extends TestCase
{
    public function testPlaceholderOverrideFallsBackToCanonicalArtifact(): void
    {
        $root = sys_get_temp_dir() . '/db-config-runtime-' . bin2hex(random_bytes(4));
        mkdir($root . '/config', 0777, true);

        file_put_contents($root . '/config/generated-install-config.json', json_encode([
            'mysql' => [
                'host' => 'live-db.internal',
                'port' => 3307,
                'name' => 'bitrix_prod',
                'user' => 'prod_user',
                'password' => 'prod_secret',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $config = DbConfig::fromRuntimeSources([
            'host' => '127.0.0.1',
            'port' => 3306,
            'name' => 'bitrix_migration',
            'user' => 'migration_user',
            'password' => 'change_me',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ], $root);

        self::assertSame('live-db.internal', $config['host']);
        self::assertSame(3307, $config['port']);
        self::assertSame('bitrix_prod', $config['name']);
        self::assertSame('prod_user', $config['user']);
        self::assertSame('prod_secret', $config['password']);
    }
}
