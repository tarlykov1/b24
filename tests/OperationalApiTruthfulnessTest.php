<?php

declare(strict_types=1);

use MigrationModule\Application\Security\SecurityGovernanceService;
use MigrationModule\Infrastructure\Http\OperationsConsoleApi;

spl_autoload_register(static function (string $class): void {
    $prefix = 'MigrationModule\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/../apps/migration-module/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

function ok(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
}

$apiProd = new OperationsConsoleApi(null, new SecurityGovernanceService(), false);
$health = $apiProd->systemHealth(['jobId' => 'job-x']);
ok(($health['status'] ?? null) === 'not_available', 'production mode does not return synthetic telemetry');
ok(($health['reason'] ?? null) === 'synthetic_telemetry_disabled_in_real_mode', 'production status is explicit');

$meta = $apiProd->meta();
ok(($meta['realtime']['transport'] ?? null) === 'polling', 'realtime contract downgraded to polling');

$apiDemo = new OperationsConsoleApi(null, new SecurityGovernanceService(), true);
$demo = $apiDemo->systemHealth(['jobId' => 'job-x']);
ok(isset($demo['throughputPerSec']), 'demo mode still provides synthetic payload');

echo "Operational API truthfulness checks passed\n";
