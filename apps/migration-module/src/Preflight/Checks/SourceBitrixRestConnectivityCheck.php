<?php

declare(strict_types=1);

namespace MigrationModule\Preflight\Checks;

use MigrationModule\Preflight\CheckContext;
use MigrationModule\Preflight\CheckInterface;
use MigrationModule\Preflight\CheckResult;
use MigrationModule\Preflight\PreflightHelper;

final class SourceBitrixRestConnectivityCheck implements CheckInterface
{
    public function __construct(private readonly CheckContext $context)
    {
    }

    public function run(): CheckResult
    {
        $url = (string) ($this->context->configValue('source', 'rest_webhook', ($_ENV['BITRIX_WEBHOOK_URL'] ?? '')));
        if ($url === '') {
            return new CheckResult('source_rest', 'warning', 'Source REST webhook is not configured; REST connectivity check skipped.');
        }

        $probe = PreflightHelper::probeRestUserCurrent($url);
        if (($probe['ok'] ?? false) !== true) {
            return new CheckResult('source_rest', 'blocked', 'Source REST user.current probe failed: ' . (string) ($probe['error'] ?? 'unknown'));
        }

        return new CheckResult('source_rest', 'ok', 'Source REST user.current probe passed.', [
            'latency_ms' => round((float) ($probe['duration_ms'] ?? 0), 2),
            'rate_limit_visible' => isset(($probe['raw'] ?? [])['time']),
        ]);
    }
}
