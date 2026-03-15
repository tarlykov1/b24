<?php

declare(strict_types=1);

namespace MigrationModule\Simulation\UI\DTO;

use MigrationModule\Simulation\Domain\SimulationRun;

final class SimulationRunDTO
{
    /** @return array<string,mixed> */
    public function fromRun(SimulationRun $run): array
    {
        return [
            'timeline' => $run->stageDurationsHours,
            'criticalPath' => $run->criticalPath,
            'riskHeatmap' => $run->riskScores,
            'sourcePressure' => $run->sourceLoadProfile,
            'targetPressure' => $run->targetLoadProfile,
            'recommended' => $run->recommendations,
        ];
    }
}
