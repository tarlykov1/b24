<?php

declare(strict_types=1);

use MigrationModule\Application\AuditDiscovery\AuditDiscoveryService;
use MigrationModule\Application\Readiness\SystemCheckService;
use MigrationModule\Application\Security\SecurityContext;
use MigrationModule\Application\Security\SecurityGovernanceService;
use MigrationModule\Infrastructure\Bitrix\BitrixRestClient;
use MigrationModule\Infrastructure\Http\AdminAuth;
use MigrationModule\Infrastructure\Http\OperationsConsoleApi;
use MigrationModule\Installation\ConfigLintService;
use MigrationModule\Installation\InstallWizardService;
use MigrationModule\Preflight\CheckContext;
use MigrationModule\Preflight\CheckRegistry;
use MigrationModule\Preflight\PreflightRunner;
use MigrationModule\Prototype\Adapter\StubSourceAdapter;
use MigrationModule\Prototype\Adapter\StubTargetAdapter;
use MigrationModule\Support\DbConfig;

require_once __DIR__ . '/../../bootstrap.php';
migration_module_bootstrap(dirname(__DIR__, 4));

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; frame-ancestors 'none'");
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$path = preg_replace('#^.*?/api\.php#', '', $path) ?: '/';

$auth = new AdminAuth();
$query = array_merge($_GET, $_POST, json_decode((string) file_get_contents('php://input'), true) ?? []);


$dbConfig = DbConfig::fromRuntimeSources([], dirname(__DIR__, 4));

if ($path === '/stream') {
    http_response_code(501);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => 'not_wired',
        'surface' => 'stream',
        'status' => 'unsupported',
        'recommended_transport' => 'polling',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($path === '/health' || $path === '/ready' || $path === '/metrics') {
    $client = null;
    if (($_ENV['BITRIX_WEBHOOK_URL'] ?? '') !== '' && ($_ENV['BITRIX_WEBHOOK_TOKEN'] ?? '') !== '') {
        $client = new BitrixRestClient((string) $_ENV['BITRIX_WEBHOOK_URL'], (string) $_ENV['BITRIX_WEBHOOK_TOKEN']);
    }
    $service = new SystemCheckService($client);
    $response = $service->check($dbConfig);
    if ($path === '/metrics') {
        header('Content-Type: text/plain; version=0.0.4');
        $pdoMetrics = new PDO(DbConfig::dsn($dbConfig), (string) $dbConfig['user'], (string) $dbConfig['password']);
        $pdoMetrics->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $jobs = (int) $pdoMetrics->query('SELECT COUNT(*) FROM jobs')->fetchColumn();
        $running = (int) $pdoMetrics->query("SELECT COUNT(*) FROM jobs WHERE status='running'")->fetchColumn();
        $paused = (int) $pdoMetrics->query("SELECT COUNT(*) FROM jobs WHERE status='paused'")->fetchColumn();
        $steps = (int) $pdoMetrics->query('SELECT COUNT(*) FROM execution_steps')->fetchColumn();
        $errors = (int) $pdoMetrics->query('SELECT COUNT(*) FROM failure_events')->fetchColumn();
        $retries = (int) $pdoMetrics->query("SELECT COUNT(*) FROM execution_steps WHERE status='retry'")->fetchColumn();
        $queueDepth = (int) $pdoMetrics->query("SELECT COUNT(*) FROM queue WHERE status IN ('pending','retry')")->fetchColumn();

        echo "migration_jobs_total {$jobs}\n";
        echo "migration_jobs_running {$running}\n";
        echo "migration_jobs_paused {$paused}\n";
        echo "migration_steps_total {$steps}\n";
        echo "migration_errors_total {$errors}\n";
        echo "migration_retries_total {$retries}\n";
        echo "migration_queue_depth {$queueDepth}\n";
        exit;
    }

    if ($path === '/ready' && (($response['ok'] ?? false) !== true)) {
        http_response_code(503);
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}



if ($path === '/preflight' || $path === '/api/preflight') {
    $configPath = (string) ($query['config'] ?? __DIR__ . '/../../../../config/migration.yaml');
    $config = [];
    if (is_file($configPath)) {
        $lines = file($configPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $section = null;
        foreach ($lines as $line) {
            $line = rtrim((string) $line);
            if ($line === '' || str_starts_with(trim($line), '#')) {
                continue;
            }
            if (preg_match('/^([a-zA-Z0-9_]+):\s*$/', $line, $m) === 1) {
                $section = $m[1];
                $config[$section] = [];
                continue;
            }
            if (preg_match('/^\s{2}([a-zA-Z0-9_]+):\s*(.+)$/', $line, $m) === 1 && $section !== null) {
                $config[$section][$m[1]] = trim((string) $m[2], " \"'");

            }
        }
    }

    $strict = (bool) filter_var((string) ($query['strict'] ?? '0'), FILTER_VALIDATE_BOOLEAN);
    $context = new CheckContext($config, new StubSourceAdapter(), new StubTargetAdapter(), null);
    $report = (new PreflightRunner(new CheckRegistry(), $context))->run($strict);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if (str_starts_with($path, '/install/')) {
    $wizard = new InstallWizardService();
    $config = is_array($query['config'] ?? null) ? $query['config'] : [];

    try {
        if ($path === '/install/check-connection') {
            $response = (new SystemCheckService())->check((array) ($config['mysql'] ?? []));
        } elseif ($path === '/install/init-schema') {
            $mysql = (array) ($config['mysql'] ?? []);
            $dbCfg = DbConfig::fromRuntimeSources($mysql, dirname(__DIR__, 4));
            $pdo = new PDO(DbConfig::dsn($dbCfg), (string) $dbCfg['user'], (string) $dbCfg['password']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $response = $wizard->initDb($pdo, __DIR__ . '/../../../../db/mysql_platform_schema.sql');
        } elseif ($path === '/install/generate-config') {
            $outputPath = (string) ($query['output'] ?? __DIR__ . '/../../../../config/generated-install-config.json');
            $mysql = (array) ($config['mysql'] ?? []);
            $payload = ['mysql' => DbConfig::fromRuntimeSources($mysql, dirname(__DIR__, 4))];
            $response = $wizard->generateConfig($payload, $outputPath);
        } else {
            $response = ['ok' => false, 'error' => 'unknown_install_endpoint', 'path' => $path];
        }
    } catch (Throwable $e) {
        $response = [
            'ok' => false,
            'status' => 'fail',
            'code' => 'installer_mysql_bootstrap_failed',
            'error' => [
                'message' => $e->getMessage(),
            ],
        ];
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($path === '/auth/login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($query['password'] ?? '');
    $ok = $auth->login($password);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => $ok, 'csrf' => $ok ? $auth->csrfToken() : null], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($path === '/auth/logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth->logout();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$auth->requireAuth();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$auth->validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($query['csrf'] ?? null))) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'csrf_invalid']);
    exit;
}

if ($dbConfig['name'] === '' || $dbConfig['user'] === '') {
    http_response_code(412);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'installer_required', 'open' => 'install.php']);
    exit;
}
$pdo = new PDO(DbConfig::dsn($dbConfig), (string) $dbConfig['user'], (string) $dbConfig['password']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$security = new SecurityGovernanceService();
$demoMode = (bool) filter_var((string) ($query['demo_mode'] ?? ($_ENV['MIGRATION_DEMO_MODE'] ?? '0')), FILTER_VALIDATE_BOOLEAN);
$api = new OperationsConsoleApi($pdo, $security, $demoMode);

$role = (string) ($query['role'] ?? 'MigrationAdmin');
$context = new SecurityContext(
    actorId: (string) ($query['actorId'] ?? 'admin-1'),
    tenantId: (string) ($query['tenantId'] ?? 'tenant-alpha'),
    workspaceId: (string) ($query['workspaceId'] ?? 'ws-core'),
    projectId: (string) ($query['projectId'] ?? 'project-crm'),
    environment: (string) ($query['environment'] ?? 'production'),
    roles: [$role],
    breakGlassActive: (bool) ($query['breakGlassActive'] ?? false),
);

$dangerousActions = ['execute migration', 'rollback', 'force repair', 'replay', 'delete skipped entities'];
if ($path === '/jobs/action' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($query['action'] ?? '');
    if (in_array($action, ['execute', 'rollback', 'force_repair', 'replay', 'delete_skipped'], true)) {
        $typed = mb_strtolower(trim((string) ($query['typedConfirmation'] ?? '')));
        $expected = mb_strtolower($action);
        if ($typed !== $expected) {
            http_response_code(422);
            $response = ['ok' => false, 'error' => 'typed_confirmation_required', 'expected' => $action];
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}


if ($path === '/hypercare/reconciliation/run' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['ok' => true, 'status' => 'queued', 'operation' => 'reconciliation_run'];
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($path === '/hypercare/integrity/scan' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['ok' => true, 'status' => 'started', 'operation' => 'integrity_scan'];
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($path === '/audit/portal') {
    $response = (new AuditDiscoveryService())->run('portal');
} elseif ($path === '/audit/summary') {
    $response = (new AuditDiscoveryService())->run('summary');
} elseif ($path === '/audit/ownership') {
    $response = (new AuditDiscoveryService())->run('ownership');
} elseif ($path === '/audit/report') {
    $response = (new AuditDiscoveryService())->run('report');
} elseif ($path === '/meta') {
    $response = $api->meta();
    $response['dangerous_actions'] = $dangerousActions;
} elseif ($path === '/system:check') {
    $client = null;
    if (($_ENV['BITRIX_WEBHOOK_URL'] ?? '') !== '' && ($_ENV['BITRIX_WEBHOOK_TOKEN'] ?? '') !== '') {
        $client = new BitrixRestClient((string) $_ENV['BITRIX_WEBHOOK_URL'], (string) $_ENV['BITRIX_WEBHOOK_TOKEN']);
    }
    $response = (new SystemCheckService($client))->check();

} else {
    if (preg_match('#^/control-center/jobs/([^/]+)$#', $path, $m) === 1) {
        $response = $api->jobDetails($m[1]);
    } elseif (preg_match('#^/control-center/jobs/([^/]+)/(pause|resume)$#', $path, $m) === 1) {
        $response = ['jobId' => $m[1], 'action' => $m[2], 'status' => $m[2] === 'pause' ? 'paused' : 'running'];
    } else {

    $response = match ($path) {
        '/dashboard' => $api->dashboard($query['jobId'] ?? null),
        '/jobs' => $api->jobs($query),
        '/jobs/details' => $api->jobDetails((string) ($query['jobId'] ?? 'latest')),
        '/control-center/jobs' => $api->jobs($query),
        '/control-center/conflicts' => $api->conflicts($query),
        '/control-center/repairs' => $api->repairs($query),
        '/conflicts' => $api->conflicts($query),
        '/integrity' => $api->integrity($query),
        '/workers' => $api->workers($query),
        '/logs' => $api->logs($query),
        '/graph' => $api->dependencyGraph($query),
        '/heatmap' => $api->heatmap($query),
        '/mapping' => $api->mapping($query),
        '/diff' => $api->diff($query),
        '/replay-preview' => $api->replayPreview($query),
        '/system-health' => $api->systemHealth($query),
        '/health' => $api->migrationHealth($query['jobId'] ?? null),
        '/hypercare/status' => $api->hypercareStatus($query['jobId'] ?? null),
        '/hypercare/integrity-report' => $api->hypercareIntegrityReport($query['jobId'] ?? null),
        '/hypercare/adoption' => $api->hypercareAdoption($query['jobId'] ?? null),
        '/hypercare/performance' => $api->hypercarePerformance($query['jobId'] ?? null),
        '/hypercare/final-report' => $api->hypercareFinalReport($query['jobId'] ?? null),
        default => ['error' => 'unknown_endpoint', 'path' => $path],
    };
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
