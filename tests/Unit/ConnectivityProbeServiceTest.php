<?php

declare(strict_types=1);

use MigrationModule\Application\Readiness\SystemCheckService;
use MigrationModule\Installation\ConnectivityProbeService;
use PHPUnit\Framework\TestCase;

final class ConnectivityProbeServiceTest extends TestCase
{
    public function testMySqlProbeRejectsMissingConfigWithStructuredClass(): void
    {
        $service = new ConnectivityProbeService();

        $result = $service->probeMySql(['host' => '', 'name' => '', 'user' => '']);

        self::assertFalse($result['ok']);
        self::assertSame('fail', $result['status']);
        self::assertSame('config', $result['error']['class']);
        self::assertSame('mysql_config_missing', $result['error']['code']);
    }

    public function testBitrixProbeRejectsMissingCredentialsWithStructuredClass(): void
    {
        $service = new ConnectivityProbeService();

        $result = $service->probeBitrix(['url' => '', 'token' => ''], 'source');

        self::assertFalse($result['ok']);
        self::assertSame('fail', $result['status']);
        self::assertSame('config', $result['error']['class']);
        self::assertSame('bitrix_credentials_missing', $result['error']['code']);
        self::assertSame('source', $result['error']['details']['surface']);
    }

    public function testSystemCheckServiceUsesCanonicalProbeResult(): void
    {
        $probe = new class extends ConnectivityProbeService {
            public function probeMySql(array $config): array
            {
                return [
                    'ok' => true,
                    'status' => 'pass',
                    'checks' => ['tcp' => true, 'auth' => true, 'schema' => true, 'write' => true],
                    'checked_at' => '2026-03-18T00:00:00+00:00',
                ];
            }
        };

        $service = new SystemCheckService($probe);
        $result = $service->check([]);

        self::assertTrue($result['ok']);
        self::assertSame('pass', $result['status']);
        self::assertTrue($result['checks']['mysql_write_permissions']);
        self::assertSame([], $result['errors']);
    }
}
