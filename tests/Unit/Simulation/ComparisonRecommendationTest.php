<?php

declare(strict_types=1);

use MigrationModule\Simulation\Application\SimulationEngine;
use MigrationModule\Simulation\Domain\SimulationInput;
use MigrationModule\Simulation\Estimator\CRMEstimator;
use MigrationModule\Simulation\Estimator\FilesEstimator;
use MigrationModule\Simulation\Estimator\TasksEstimator;
use MigrationModule\Simulation\Estimator\UsersEstimator;
use MigrationModule\Simulation\Scenario\ScenarioBuilder;

require_once __DIR__ . '/../../bootstrap_simulation.php';

it('compares scenarios and returns leaders', function (): void {
    $engine = new SimulationEngine([new UsersEstimator(), new CRMEstimator(), new TasksEstimator(), new FilesEstimator()]);
    $builder = new ScenarioBuilder();
    $input = new SimulationInput(['users' => 500, 'deals' => 2000, 'tasks' => 4000, 'files' => 6000], ['custom_fields' => 120], ['files' => ['tasks']], [], []);

    $comparison = $engine->compare($input, array_slice($builder->buildCandidates($input), 0, 3));
    $data = $comparison->toArray();

    assert(isset($data['leaders']['fastest']));
    assert(isset($data['leaders']['safest']));
});
