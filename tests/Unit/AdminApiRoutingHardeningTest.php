<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminApiRoutingHardeningTest extends TestCase
{
    public function testLegacyJobsRoutesAreCompatibilityOnly(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../apps/migration-module/ui/admin/api.php');

        self::assertStringContainsString("'error' => 'deprecated_endpoint'", $source);
        self::assertStringContainsString("'class' => 'compatibility_only'", $source);
        self::assertStringNotContainsString("'/jobs' => $api->jobs", $source);
        self::assertStringNotContainsString("'/jobs/details' => $api->jobDetails", $source);
        self::assertStringNotContainsString("'/control-center/jobs' => $api->jobs", $source);
    }
}
