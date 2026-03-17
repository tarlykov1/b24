<?php

declare(strict_types=1);

use MigrationModule\Support\DbConfig;

$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require_once $vendorAutoload;
}

$dbConfig = DbConfig::fromRuntimeSources([], dirname(__DIR__));
$hasDbEnv = (string) ($dbConfig['name'] ?? '') !== '' && (string) ($dbConfig['user'] ?? '') !== '';

if (!$hasDbEnv) {
    require_once __DIR__ . '/installer.php';
    exit;
}

require_once __DIR__ . '/../apps/migration-module/ui/admin/index.php';
