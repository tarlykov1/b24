<?php

declare(strict_types=1);

namespace MigrationModule\Simulation\Estimator;

final class TasksEstimator extends AbstractEntityEstimator
{
    protected function baseline(): array
    {
        return ['read_ms' => 2.7, 'transform_ms' => 2.1, 'write_ms' => 3.0, 'verify_ms' => 1.5];
    }

    protected function supportedEntities(): array
    {
        return ['tasks', 'comments', 'activity_timeline'];
    }
}
