<?php

declare(strict_types=1);

namespace MigrationModule\Simulation\Estimator;

final class UsersEstimator extends AbstractEntityEstimator
{
    protected function baseline(): array
    {
        return ['read_ms' => 2.2, 'transform_ms' => 1.8, 'write_ms' => 2.5, 'verify_ms' => 1.1];
    }

    protected function supportedEntities(): array
    {
        return ['users', 'departments', 'groups'];
    }
}
