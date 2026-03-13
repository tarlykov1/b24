<?php

declare(strict_types=1);

namespace MigrationModule\Domain\Config;

final class RunMode
{
    public const INITIAL_IMPORT = 'initial_import';
    public const DRY_RUN = 'dry_run';
    public const INCREMENTAL_SYNC = 'incremental_sync';
    public const DELTA_SYNC = 'delta_sync';
    public const RECONCILIATION = 'reconciliation';
    public const VERIFICATION = 'verification';

    public static function isSupported(string $mode): bool
    {
        return \in_array($mode, [
            self::INITIAL_IMPORT,
            self::DRY_RUN,
            self::INCREMENTAL_SYNC,
            self::DELTA_SYNC,
            self::RECONCILIATION,
            self::VERIFICATION,
        ], true);
    }
}
