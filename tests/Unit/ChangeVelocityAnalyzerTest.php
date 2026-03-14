<?php

declare(strict_types=1);

use MigrationModule\Audit\ChangeVelocityAnalyzer;

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

it('builds velocity report and artifacts', function (): void {
    $dbPath = sys_get_temp_dir() . '/velocity-audit-' . uniqid() . '.sqlite';
    $pdo = new PDO('sqlite:' . $dbPath);
    $sql = file_get_contents(__DIR__ . '/../Fixtures/velocity_audit_fixture.sql');
    $pdo->exec((string) $sql);

    $report = (new ChangeVelocityAnalyzer())->analyze($pdo, null, __DIR__ . '/../Fixtures', 30, 1000);

    assert(isset($report['entities']['tasks']));
    assert(isset($report['velocity_heatmap']));
    assert(isset($report['migration_strategy']['recommended_workers']));
    assert(is_file('reports/change_velocity_report.json'));
    assert(is_file('reports/velocity_heatmap.json'));

    @unlink($dbPath);
});

function it(string $title, callable $fn): void
{
    $fn();
    echo "[ok] {$title}\n";
}
