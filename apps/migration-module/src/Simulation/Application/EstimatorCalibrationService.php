<?php

declare(strict_types=1);

namespace MigrationModule\Simulation\Application;

final class EstimatorCalibrationService
{
    /** @param array<string,float> $coefficients @param array<string,float> $actual @param array<string,float> $predicted @return array<string,float> */
    public function calibrate(array $coefficients, array $actual, array $predicted): array
    {
        foreach ($coefficients as $key => $value) {
            $a = $actual[$key] ?? null;
            $p = $predicted[$key] ?? null;
            if ($a === null || $p === null || $p <= 0.0) {
                continue;
            }
            $ratio = $a / $p;
            $coefficients[$key] = round(($value * 0.7) + ($ratio * 0.3), 4);
        }

        return $coefficients;
    }
}
