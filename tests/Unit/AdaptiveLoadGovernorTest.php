<?php

declare(strict_types=1);

use MigrationModule\Application\Orchestrator\AdaptiveLoadGovernor;
use PHPUnit\Framework\TestCase;

final class AdaptiveLoadGovernorTest extends TestCase
{
    public function testAutoModeFallsBackAndScalesDownOnCpuAndErrors(): void
    {
        $governor = new AdaptiveLoadGovernor(30, 35, 200, 8);
        $result = $governor->tune([
            'runtime_mode' => 'day',
            'source_p95_ms' => 1800,
            'error_rate' => 0.21,
            'cpu_load' => 0.92,
            'rate_limited' => true,
        ]);

        self::assertSame('day', $result['mode']);
        self::assertLessThan(30, $result['source_rps']);
        self::assertLessThan(8, $result['concurrency']);
    }

    public function testNightWeekendModeAllowsHigherCeilings(): void
    {
        $governor = new AdaptiveLoadGovernor(40, 45, 500, 12);
        $result = $governor->tune([
            'runtime_mode' => 'night_weekend_high_speed',
            'source_p95_ms' => 220,
            'error_rate' => 0.01,
            'cpu_load' => 0.43,
            'rate_limited' => false,
        ]);

        self::assertSame('night_weekend_high_speed', $result['mode']);
        self::assertGreaterThan(40, $result['source_rps']);
        self::assertGreaterThan(12, $result['concurrency']);
    }
}
