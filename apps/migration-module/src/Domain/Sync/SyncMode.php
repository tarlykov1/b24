<?php

declare(strict_types=1);

namespace MigrationModule\Domain\Sync;

final class SyncMode
{
    public const FULL_MIGRATION = 'FULL_MIGRATION';
    public const INCREMENTAL_SYNC = 'INCREMENTAL_SYNC';
}
