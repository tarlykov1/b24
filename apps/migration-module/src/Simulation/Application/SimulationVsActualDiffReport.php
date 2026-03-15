<?php

declare(strict_types=1);

namespace MigrationModule\Simulation\Application;

final class SimulationVsActualDiffReport
{
    /** @param array<string,float> $predicted @param array<string,float> $actual @return array<string,mixed> */
    public function build(array $predicted, array $actual): array
    {
        $diff = [];
        foreach ($predicted as $metric => $value) {
            $fact = $actual[$metric] ?? 0.0;
            $delta = $fact - $value;
            $diff[$metric] = [
                'predicted' => $value,
                'actual' => $fact,
                'delta' => round($delta, 2),
                'delta_percent' => $value > 0 ? round(($delta / $value) * 100, 2) : null,
            ];
        }

        return ['metrics' => $diff];
    }
}
