<?php

declare(strict_types=1);

use MigrationModule\Simulation\Application\SimulationEngine;
use MigrationModule\Simulation\Domain\SimulationInput;
use MigrationModule\Simulation\Estimator\CRMEstimator;
use MigrationModule\Simulation\Estimator\FilesEstimator;
use MigrationModule\Simulation\Estimator\TasksEstimator;
use MigrationModule\Simulation\Estimator\UsersEstimator;
use MigrationModule\Simulation\Report\SimulationReportBuilder;
use MigrationModule\Simulation\Scenario\ScenarioBuilder;

require_once __DIR__ . '/../../bootstrap_simulation.php';

it('builds stable report structure for ui/json usage', function (): void {
    $engine = new SimulationEngine([new UsersEstimator(), new CRMEstimator(), new TasksEstimator(), new FilesEstimator()]);
    $builder = new ScenarioBuilder();
    $report = new SimulationReportBuilder();

    $input = new SimulationInput(['users' => 100, 'deals' => 400, 'tasks' => 1000, 'files' => 2000], ['custom_fields' => 50], ['files' => ['tasks']], [], []);
    $run = $engine->run($input, $builder->buildCandidates($input)[0]);

    $json = $report->toJson($run);
    $md = $report->toMarkdown($run);

    assert(isset($json['summary']['duration_hours']));
    assert(str_contains($md, '# Simulation Report'));
});
