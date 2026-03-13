<?php

declare(strict_types=1);

namespace MigrationModule\Application\Control;

final class MigrationControlService
{
    public function start(string $jobId): void
    {
        // TODO: transition ready -> running.
    }

    public function pause(string $jobId): void
    {
        // TODO: transition running -> pausing -> paused after batch drain.
    }

    public function resume(string $jobId): void
    {
        // TODO: transition paused -> resuming -> running.
    }

    public function softStop(string $jobId): void
    {
        // TODO: transition running -> stopping -> stopped after checkpoint.
    }
}
