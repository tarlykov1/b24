<?php

declare(strict_types=1);

use MigrationModule\Application\Intelligence\DependencyGraphAnalyzer;
use MigrationModule\Application\Intelligence\MigrationIntelligenceEngine;

if (is_file(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'MigrationModule\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $path = __DIR__ . '/../../apps/migration-module/src/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    });
}

it('generates strategy output from audit report input', function (): void {
    $engine = new MigrationIntelligenceEngine();
    $result = $engine->generate(sampleAudit());

    assert(($result['engine'] ?? '') === 'migration_intelligence_engine');
    assert(isset($result['migration_strategy_plan']['entity_migration_order']));
    assert(isset($result['risk_analysis']['items']));
    assert(isset($result['performance_plan']['recommended_settings']['workers']));
    assert(isset($result['strategy_profiles']['SAFE_MODE']));
    assert(($result['runtime_overrides']['safety']['always_prioritize_safe_migration'] ?? false) === true);
});

it('visualizes migration dependency graph and returns topological details', function (): void {
    $engine = new MigrationIntelligenceEngine();
    $visual = $engine->visualize(sampleAudit());

    assert(str_contains((string) ($visual['dot'] ?? ''), 'digraph MigrationDependencies'));
    assert(in_array('users', (array) ($visual['safe_order'] ?? []), true));
    assert(isset($visual['levels']));
});

it('detects cycles in dependency graph analyzer', function (): void {
    $analyzer = new DependencyGraphAnalyzer();
    $analysis = $analyzer->analyze([
        'users' => ['files'],
        'files' => ['users'],
        'tasks' => ['users'],
    ]);

    assert(count($analysis['cycles']) > 0);
});

/** @return array<string,mixed> */
function sampleAudit(): array
{
    return [
        'audit_type' => 'bitrix_source_discovery',
        'entity_counts' => [
            'users' => 1000,
            'departments' => 20,
            'groups_projects' => 60,
            'contacts' => 12000,
            'companies' => 5000,
            'deals' => 60000,
            'leads' => 3000,
            'smart_processes' => 1200,
            'tasks' => 45000,
            'files' => 110000,
        ],
        'custom_fields' => array_fill(0, 100, 'UF_X'),
        'crm_pipelines' => array_fill(0, 11, ['id' => 1]),
        'broken_relations' => ['orphan_attachment_references' => 50, 'tasks_with_attachments' => 400],
        'file_storage_locations' => ['storages' => ['disk', 'upload']],
        'orphan_files' => 300,
        'raw_audit' => ['files' => ['total_size_gb' => 250]],
        'user_activity' => ['active_last_30_days' => 730],
        'migration_complexity_score' => 78,
        'estimated_runtime_hours' => 13.5,
        'risk_summary' => ['risk_level' => 'HIGH'],
        'inactive_users' => 220,
    ];
}

function it(string $title, callable $fn): void
{
    $fn();
    echo "[ok] {$title}\n";
}
