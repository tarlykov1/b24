<?php

declare(strict_types=1);

namespace MigrationModule\Application\Logging;

use MigrationModule\Domain\Log\LogRecord;

final class MigrationLogger
{
    public function log(LogRecord $record): void
    {
        // TODO: write structured logs to migration_log and system sink.
    }
}
