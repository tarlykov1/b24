<?php

declare(strict_types=1);

namespace MigrationModule\Preflight\Checks;

use MigrationModule\Preflight\CheckContext;
use MigrationModule\Preflight\CheckInterface;
use MigrationModule\Preflight\CheckResult;
use MigrationModule\Preflight\PreflightHelper;

final class RestRateLimitDetectionCheck implements CheckInterface
{
    public function __construct(private readonly CheckContext $context)
    {
    }

    public function run(): CheckResult
    {
        $url = (string) ($this->context->configValue('source', 'rest_webhook', ($_ENV['BITRIX_WEBHOOK_URL'] ?? '')));
        if ($url === '') {
            return new CheckResult('rest_rate_limit', 'warning', 'Source REST webhook is not configured; rate limit estimation skipped.');
        }

        $durations = [];
        for ($i = 0; $i < 4; $i++) {
            $probe = PreflightHelper::probeRestUserCurrent($url);
            if (($probe['ok'] ?? false) !== true) {
                return new CheckResult('rest_rate_limit', 'warning', 'Unable to estimate REST RPS safely: ' . (string) ($probe['error'] ?? 'unknown'));
            }
            $durations[] = max(1.0, (float) ($probe['duration_ms'] ?? 1.0));
            usleep(120000);
        }

        $avgMs = array_sum($durations) / count($durations);
        $recommended = max(1, (int) floor(1000 / ($avgMs * 1.2)));

        return new CheckResult('rest_rate_limit', 'ok', 'REST throttling profile estimated using low-volume probes.', [
            'recommended_rate_limit' => $recommended,
            'avg_latency_ms' => round($avgMs, 2),
        ]);
    }
}
