<?php

declare(strict_types=1);

use MigrationModule\Infrastructure\Http\OperationsConsoleApi;
use PHPUnit\Framework\TestCase;

final class OperationsConsoleApiTest extends TestCase
{
    public function testDashboardContainsCoreStats(): void
    {
        $api = new OperationsConsoleApi(null);
        $dashboard = $api->dashboard();

        self::assertArrayHasKey('stats', $dashboard);
        self::assertArrayHasKey('latestEvents', $dashboard);
        self::assertArrayHasKey('totalJobs', $dashboard['stats']);
        self::assertArrayHasKey('queueDepth', $dashboard['stats']);
        self::assertArrayHasKey('sourceTargetLagSec', $dashboard['stats']);
        self::assertArrayHasKey('featureFlags', $dashboard);
    }

    public function testJobsEndpointSupportsPagingContract(): void
    {
        $api = new OperationsConsoleApi(null);
        $jobs = $api->jobs(['limit' => 7, 'offset' => 5]);

        self::assertCount(7, $jobs['items']);
        self::assertSame(7, $jobs['limit']);
        self::assertSame(5, $jobs['offset']);
    }

    public function testHeatmapReturnsCellsForDrillDown(): void
    {
        $api = new OperationsConsoleApi(null);
        $heatmap = $api->heatmap(['x' => 'entityType', 'y' => 'phase']);

        self::assertNotEmpty($heatmap['cells']);
        self::assertArrayHasKey('count', $heatmap['cells'][0]);
    }

    public function testMetaContainsFeatureFlagsAndRoles(): void
    {
        $api = new OperationsConsoleApi(null);
        $meta = $api->meta();

        self::assertArrayHasKey('featureFlags', $meta);
        self::assertArrayHasKey('roles', $meta);
        self::assertContains('operator', $meta['roles']);
    }

    public function testJobDetailsContainTabbedDataContract(): void
    {
        $api = new OperationsConsoleApi(null);
        $details = $api->jobDetails('job-42');

        self::assertSame('job-42', $details['jobId']);
        self::assertArrayHasKey('overview', $details);
        self::assertArrayHasKey('timeline', $details);
        self::assertArrayHasKey('syncStatus', $details);
    }
}
