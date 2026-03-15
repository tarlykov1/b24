<?php

declare(strict_types=1);

namespace MigrationModule\Simulation\Estimator;

final class CRMEstimator extends AbstractEntityEstimator
{
    protected function baseline(): array
    {
        return ['read_ms' => 3.8, 'transform_ms' => 3.2, 'write_ms' => 4.1, 'verify_ms' => 2.0];
    }

    protected function supportedEntities(): array
    {
        return ['contacts', 'companies', 'deals', 'leads', 'smart_processes'];
    }
}
