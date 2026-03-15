<?php

declare(strict_types=1);

use MigrationModule\Simulation\Domain\SimulationInput;
use MigrationModule\Simulation\Scenario\ScenarioBuilder;

require_once __DIR__ . '/../../bootstrap_simulation.php';

it('builds preset scenarios including safe and fastest profiles', function (): void {
    $builder = new ScenarioBuilder();
    $scenarios = $builder->buildCandidates(new SimulationInput([], [], [], [], []));

    assert(count($scenarios) >= 9);
    $names = array_map(static fn ($s): string => $s->name, $scenarios);
    assert(in_array('SafeSource', $names, true));
    assert(in_array('FastCutover', $names, true));
});
