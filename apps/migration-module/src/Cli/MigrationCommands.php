<?php

declare(strict_types=1);

namespace MigrationModule\Cli;

final class MigrationCommands
{
    public function preflight(): int { return 0; }

    public function audit(): int { return 0; }

    public function createJob(): int { return 0; }

    public function startJob(): int { return 0; }

    public function pauseJob(): int { return 0; }

    public function resumeJob(): int { return 0; }

    public function stopJob(): int { return 0; }

    public function diff(): int { return 0; }

    public function verify(): int { return 0; }
}
