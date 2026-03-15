<?php

declare(strict_types=1);

use MigrationModule\Simulation\Application\EstimatorCalibrationService;

require_once __DIR__ . '/../../bootstrap_simulation.php';

it('calibrates coefficients towards actual runtime', function (): void {
    $service = new EstimatorCalibrationService();
    $result = $service->calibrate(['users' => 1.0], ['users' => 12.0], ['users' => 10.0]);
    assert(($result['users'] ?? 0) > 1.0);
});
