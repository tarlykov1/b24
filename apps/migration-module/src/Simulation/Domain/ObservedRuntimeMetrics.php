<?php

declare(strict_types=1);

namespace MigrationModule\Simulation\Domain;

final class ObservedRuntimeMetrics
{
    /** @param array<string,float> $metrics */
    public function __construct(public readonly string $profile, public readonly array $metrics)
    {
    }
}
