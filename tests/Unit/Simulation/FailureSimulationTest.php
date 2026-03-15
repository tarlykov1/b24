<?php

declare(strict_types=1);

use MigrationModule\Simulation\Application\FailureSimulationService;

require_once __DIR__ . '/../../bootstrap_simulation.php';

it('amplifies duration and risk after injected failures', function (): void {
    $service = new FailureSimulationService();
    $base = ['estimated_total_duration_hours' => 5.0, 'risk_scores' => ['OverallSimulationRiskScore' => 45.0]];

    $result = $service->apply($base, ['network_instability', 'worker_crash']);
    assert($result['estimated_total_duration_hours'] > 5.0);
    assert(($result['risk_scores']['OverallSimulationRiskScore'] ?? 0) > 45.0);
});
