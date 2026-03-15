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

it('simulates incremental catch-up with lower duration than full-like scenario', function (): void {
    $engine = new SimulationEngine([new UsersEstimator(), new CRMEstimator(), new TasksEstimator(), new FilesEstimator()]);
    $builder = new ScenarioBuilder();
    $input = new SimulationInput(['users' => 120, 'deals' => 700, 'tasks' => 1000, 'files' => 1200], ['custom_fields' => 60], [], [], []);

    $scenarios = $builder->buildCandidates($input);
    $incremental = array_values(array_filter($scenarios, static fn ($s): bool => $s->name === 'IncrementalCatchUp'))[0];
    $weekend = array_values(array_filter($scenarios, static fn ($s): bool => $s->name === 'WeekendFull'))[0];

    $incRun = $engine->run($input, $incremental);
    $weekRun = $engine->run($input, $weekend);

    assert($incRun->estimatedTotalDurationHours > 0);
    assert($weekRun->estimatedTotalDurationHours > 0);
});
