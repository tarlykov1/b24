<?php

declare(strict_types=1);

namespace MigrationModule\Preflight\Checks;

use MigrationModule\Preflight\CheckContext;
use MigrationModule\Preflight\CheckInterface;
use MigrationModule\Preflight\CheckResult;
use MigrationModule\Preflight\PreflightHelper;

final class TargetBitrixRestAccessCheck implements CheckInterface
{
    public function __construct(private readonly CheckContext $context)
    {
    }

    public function run(): CheckResult
    {
        $url = (string) ($this->context->configValue('target', 'rest_webhook', ''));
        if ($url === '') {
            return new CheckResult('target_rest', 'warning', 'Target REST webhook is not configured; target access check skipped.');
        }

        $probe = PreflightHelper::probeRestUserCurrent($url);
        if (($probe['ok'] ?? false) !== true) {
            return new CheckResult('target_rest', 'blocked', 'Target REST user.current probe failed: ' . (string) ($probe['error'] ?? 'unknown'));
        }

        return new CheckResult('target_rest', 'ok', 'Target REST API enabled and authenticated.', [
            'latency_ms' => round((float) ($probe['duration_ms'] ?? 0), 2),
            'write_permissions_hint' => 'validated through target DB write probe',
        ]);
    }
}
