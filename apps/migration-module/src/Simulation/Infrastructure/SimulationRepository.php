<?php

declare(strict_types=1);

namespace MigrationModule\Simulation\Infrastructure;

use MigrationModule\Simulation\Domain\SimulationRun;
use MigrationModule\Simulation\Domain\SimulationScenario;
use PDO;

final class SimulationRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function saveScenario(string $auditId, string $policyVersion, SimulationScenario $scenario, array $inputSnapshot): void
    {
        $stmt = $this->pdo->prepare('INSERT OR REPLACE INTO simulation_scenarios(id, name, migration_mode, parameters_json, based_on_audit_id, policy_version, input_snapshot_json, created_at) VALUES(:id,:name,:migration_mode,:parameters_json,:audit_id,:policy_version,:input_snapshot_json,CURRENT_TIMESTAMP)');
        $stmt->execute([
            'id' => $scenario->id,
            'name' => $scenario->name,
            'migration_mode' => $scenario->migrationMode,
            'parameters_json' => json_encode($scenario->parameters, JSON_UNESCAPED_UNICODE),
            'audit_id' => $auditId,
            'policy_version' => $policyVersion,
            'input_snapshot_json' => json_encode($inputSnapshot, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function saveRun(SimulationRun $run): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO simulation_runs(id, scenario_id, result_json, created_at) VALUES(:id,:scenario_id,:result_json,CURRENT_TIMESTAMP)');
        $stmt->execute([
            'id' => 'simrun_' . bin2hex(random_bytes(5)),
            'scenario_id' => $run->scenarioId,
            'result_json' => json_encode($run->toArray(), JSON_UNESCAPED_UNICODE),
        ]);
    }

    /** @param array<string,mixed> $comparison */
    public function saveComparison(string $name, array $comparison): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO simulation_comparisons(id, name, result_json, created_at) VALUES(:id,:name,:result_json,CURRENT_TIMESTAMP)');
        $stmt->execute([
            'id' => 'simcmp_' . bin2hex(random_bytes(5)),
            'name' => $name,
            'result_json' => json_encode($comparison, JSON_UNESCAPED_UNICODE),
        ]);
    }
}
