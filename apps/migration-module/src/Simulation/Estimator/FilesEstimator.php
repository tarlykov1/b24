<?php

declare(strict_types=1);

namespace MigrationModule\Simulation\Estimator;

final class FilesEstimator extends AbstractEntityEstimator
{
    protected function baseline(): array
    {
        return ['read_ms' => 6.0, 'transform_ms' => 2.8, 'write_ms' => 8.5, 'verify_ms' => 3.6];
    }

    protected function supportedEntities(): array
    {
        return ['files', 'attachments'];
    }
}
