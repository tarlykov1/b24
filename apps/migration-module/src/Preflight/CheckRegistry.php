<?php

declare(strict_types=1);

namespace MigrationModule\Preflight;

use MigrationModule\Preflight\Checks\DiskCapacityCheck;
use MigrationModule\Preflight\Checks\EntityCountDiscoveryCheck;
use MigrationModule\Preflight\Checks\MigrationConfigValidationCheck;
use MigrationModule\Preflight\Checks\RestRateLimitDetectionCheck;
use MigrationModule\Preflight\Checks\SourceBitrixRestConnectivityCheck;
use MigrationModule\Preflight\Checks\SourceDatabaseConnectivityCheck;
use MigrationModule\Preflight\Checks\SourceFilesystemAccessCheck;
use MigrationModule\Preflight\Checks\TargetBitrixRestAccessCheck;
use MigrationModule\Preflight\Checks\TargetDatabaseAccessCheck;
use MigrationModule\Preflight\Checks\TargetPortalCleanlinessCheck;

final class CheckRegistry
{
    /** @return list<CheckInterface> */
    public function all(CheckContext $context): array
    {
        return [
            new MigrationConfigValidationCheck($context),
            new SourceBitrixRestConnectivityCheck($context),
            new SourceDatabaseConnectivityCheck($context),
            new SourceFilesystemAccessCheck($context),
            new TargetBitrixRestAccessCheck($context),
            new TargetDatabaseAccessCheck($context),
            new TargetPortalCleanlinessCheck($context),
            new DiskCapacityCheck($context),
            new RestRateLimitDetectionCheck($context),
            new EntityCountDiscoveryCheck($context),
        ];
    }
}
