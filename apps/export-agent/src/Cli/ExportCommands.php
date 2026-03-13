<?php

declare(strict_types=1);

namespace ExportAgent\Cli;

final class ExportCommands
{
    public function preflight(): int
    {
        // TODO: run source-side preflight checks.
        return 0;
    }

    public function audit(): int
    {
        // TODO: run source-side inventory collection.
        return 0;
    }

    public function batch(): int
    {
        // TODO: execute batched export.
        return 0;
    }

    public function delta(): int
    {
        // TODO: export changes since checkpoint.
        return 0;
    }
}
