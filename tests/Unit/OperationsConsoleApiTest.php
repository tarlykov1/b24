<?php

declare(strict_types=1);

use MigrationModule\Infrastructure\Http\OperationsConsoleApi;
use PHPUnit\Framework\TestCase;

final class OperationsConsoleApiTest extends TestCase
{
    public function testDashboardInRealModeIsHonestAboutAvailability(): void
    {
        $api = new OperationsConsoleApi(null);
        $jobs = $api->jobs(['limit' => 7, 'offset' => 5]);

        self::assertSame('not_available', $jobs['status']);
        self::assertTrue($jobs['demo_only']);
        self::assertCount(0, $jobs['items']);
    }

    public function testDemoModeKeepsSyntheticContractsExplicitly(): void
    {
        $api = new OperationsConsoleApi(null, null, true);
        $dashboard = $api->dashboard();

        self::assertArrayHasKey('stats', $dashboard);
        self::assertArrayHasKey('latestEvents', $dashboard);
    }

    public function testRealModeDisablesSyntheticPanels(): void
    {
        $api = new OperationsConsoleApi(null);
        $details = $api->jobDetails('job-42');

        self::assertSame('not_available', $details['status']);
        self::assertSame('job_details', $details['surface']);
        self::assertTrue($details['demo_only']);
    }
}
