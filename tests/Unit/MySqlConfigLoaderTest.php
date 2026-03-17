<?php

declare(strict_types=1);

use MigrationModule\Prototype\ConfigLoader;
use PHPUnit\Framework\TestCase;

final class MySqlConfigLoaderTest extends TestCase
{
    public function testDefaultStorageIsMysql(): void
    {
        $path = sys_get_temp_dir() . '/migration-config-' . bin2hex(random_bytes(4)) . '.yml';
        file_put_contents($path, "batch_size: 2\n");

        $config = (new ConfigLoader())->load($path);

        self::assertSame('mysql', $config['storage']['driver']);
        self::assertArrayHasKey('host', $config['storage']);
        self::assertArrayHasKey('port', $config['storage']);
        self::assertArrayHasKey('name', $config['storage']);
    }
}
