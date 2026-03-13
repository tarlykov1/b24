<?php

declare(strict_types=1);

namespace MigrationModule\Domain\Config;

final class RunMode
{
    public const INITIAL_IMPORT = 'initial_import';
    public const SYNC = 'sync';

    public static function isSupported(string $mode): bool
    {
        return \in_array($mode, [self::INITIAL_IMPORT, self::SYNC], true);
    }
}
