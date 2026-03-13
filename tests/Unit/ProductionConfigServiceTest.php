<?php

declare(strict_types=1);

use MigrationModule\Application\Config\ProductionConfigService;
use PHPUnit\Framework\TestCase;

final class ProductionConfigServiceTest extends TestCase
{
    public function testLoadsConfigWithNestedRateLimitAndRetryPolicy(): void
    {
        $service = new ProductionConfigService();
        $config = $service->load('migration.config.yml');

        self::assertSame('balanced', $config['profile']);
        self::assertSame(35, $config['rate_limit']['source_rpm']);
        self::assertSame(40, $config['rate_limit']['target_rpm']);
        self::assertSame(8, $config['rate_limit']['heavy_rpm']);
        self::assertSame(100, $config['batch_size']);
        self::assertSame(500, $config['chunk_size']);
        self::assertSame(6, $config['retry_policy']['max_retries']);
        self::assertSame(200, $config['retry_policy']['base_delay_ms']);
        self::assertSame(5000, $config['retry_policy']['max_delay_ms']);
    }
}
