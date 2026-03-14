<?php

declare(strict_types=1);

use MigrationModule\Application\AuditDiscovery\AuditDiscoveryService;
use MigrationModule\Application\Readiness\SystemCheckService;
use MigrationModule\Application\Security\SecurityContext;
use MigrationModule\Application\Security\SecurityGovernanceService;
use MigrationModule\Infrastructure\Bitrix\BitrixRestClient;
use MigrationModule\Infrastructure\Http\AdminAuth;
use MigrationModule\Infrastructure\Http\OperationsConsoleApi;

$vendorAutoload = __DIR__ . '/../../../../vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require_once $vendorAutoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'MigrationModule\\';
        if (str_starts_with($class, $prefix)) {
            $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
            $file = __DIR__ . '/../../src/' . $relative . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        }
    });
}

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

if ($path === '/health' || $path === '/ready') {
    $client = null;
    if (($_ENV['BITRIX_WEBHOOK_URL'] ?? '') !== '' && ($_ENV['BITRIX_WEBHOOK_TOKEN'] ?? '') !== '') {
        $client = new BitrixRestClient((string) $_ENV['BITRIX_WEBHOOK_URL'], (string) $_ENV['BITRIX_WEBHOOK_TOKEN']);
    }
    $service = new SystemCheckService($client);
    $response = $service->check(__DIR__ . '/../../../../.prototype/migration.sqlite');
    if ($path === '/ready' && (($response['ok'] ?? false) !== true)) {
        http_response_code(503);
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

$dbPath = __DIR__ . '/../../../../.prototype/migration.sqlite';
$pdo = null;
if (is_file($dbPath)) {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

$security = new SecurityGovernanceService();
$api = new OperationsConsoleApi($pdo, $security);

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

if ($path === '/audit/portal') {
    $response = (new AuditDiscoveryService())->run('portal');
} elseif ($path === '/audit/summary') {
    $response = (new AuditDiscoveryService())->run('summary');
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
    $response = (new SystemCheckService($client))->check($dbPath);
} else {
    $response = match ($path) {
        '/dashboard' => $api->dashboard($query['jobId'] ?? null),
        '/jobs' => $api->jobs($query),
        '/jobs/details' => $api->jobDetails((string) ($query['jobId'] ?? 'latest')),
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
        default => ['error' => 'unknown_endpoint', 'path' => $path],
    };
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
