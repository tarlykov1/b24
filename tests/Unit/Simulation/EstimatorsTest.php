<?php

declare(strict_types=1);

use MigrationModule\Simulation\Domain\SimulationInput;
use MigrationModule\Simulation\Domain\SimulationScenario;
use MigrationModule\Simulation\Estimator\CRMEstimator;
use MigrationModule\Simulation\Estimator\FilesEstimator;
use MigrationModule\Simulation\Estimator\TasksEstimator;
use MigrationModule\Simulation\Estimator\UsersEstimator;

require_once __DIR__ . '/../../bootstrap_simulation.php';

it('estimates base entities with non-zero totals', function (): void {
    $input = new SimulationInput(['users' => 100], ['custom_fields' => 100], [], [], []);
    $scenario = new SimulationScenario('s1', 'test', 'full', ['worker_count' => 6]);

    $estimators = [new UsersEstimator(), new CRMEstimator(), new TasksEstimator(), new FilesEstimator()];
    foreach ($estimators as $estimator) {
        $entity = $estimator instanceof CRMEstimator ? 'deals' : ($estimator instanceof TasksEstimator ? 'tasks' : ($estimator instanceof FilesEstimator ? 'files' : 'users'));
        $cost = $estimator->estimate($entity, 100, $input, $scenario);
        assert(($cost['total_ms'] ?? 0) > 0);
    }
});
