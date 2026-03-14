<?php

declare(strict_types=1);

use MigrationModule\Application\Security\SecurityContext;
use MigrationModule\Application\Security\SecurityGovernanceService;
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

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Tenant-Id, X-Workspace-Id');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$path = preg_replace('#^.*?/api\.php#', '', $path) ?: '/';

$dbPath = __DIR__ . '/../../../../.prototype/migration.sqlite';
$pdo = null;
if (is_file($dbPath)) {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

$query = array_merge($_GET, json_decode((string) file_get_contents('php://input'), true) ?? []);
$security = new SecurityGovernanceService();
$api = new OperationsConsoleApi($pdo, $security);

$role = (string) ($query['role'] ?? 'MigrationOperator');
$context = new SecurityContext(
    actorId: (string) ($query['actorId'] ?? 'operator-1'),
    tenantId: (string) ($query['tenantId'] ?? 'tenant-alpha'),
    workspaceId: (string) ($query['workspaceId'] ?? 'ws-core'),
    projectId: (string) ($query['projectId'] ?? 'project-crm'),
    environment: (string) ($query['environment'] ?? 'production'),
    roles: [$role],
    breakGlassActive: (bool) ($query['breakGlassActive'] ?? false),
);

if ($path === '/stream') {
    $topic = (string) ($query['topic'] ?? 'logs');
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');

    for ($i = 0; $i < 20; ++$i) {
        $payload = match ($topic) {
            'workers' => $api->workers($query),
            'dashboard' => $api->dashboard($query['jobId'] ?? null),
            default => $api->logs($query),
        };
        echo "event: {$topic}\n";
        echo 'data: ' . json_encode(['topic' => $topic, 'data' => $payload, 'ts' => date(DATE_ATOM)], JSON_THROW_ON_ERROR) . "\n\n";
        @ob_flush();
        flush();
        usleep(1000 * 1200);
    }

    exit;
}

if ($path === '/security/governance') {
    $response = $security->governanceOverview($context);
} elseif ($path === '/security/capabilities') {
    $response = $security->capabilityMap($context, ['tenantId' => $context->tenantId, 'workspaceId' => $context->workspaceId, 'environment' => $context->environment]);
} elseif ($path === '/security/authorize' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = $security->authorize(
        $context,
        (string) ($query['permission'] ?? 'jobs.view'),
        [
            'tenantId' => (string) ($query['resourceTenantId'] ?? $context->tenantId),
            'workspaceId' => (string) ($query['resourceWorkspaceId'] ?? $context->workspaceId),
            'environment' => (string) ($query['resourceEnvironment'] ?? $context->environment),
        ],
        (bool) ($query['highRisk'] ?? false),
    )->toArray();
} elseif ($path === '/approvals/submit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = $security->submitApprovalRequest(
        $context,
        (string) ($query['actionType'] ?? 'job.rollback'),
        (string) ($query['reason'] ?? 'No reason provided'),
        (array) ($query['payload'] ?? []),
        (string) ($query['risk'] ?? 'medium'),
    );
} elseif ($path === '/approvals/decide' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = $security->decideApproval(
        $context,
        (string) ($query['approvalId'] ?? ''),
        (string) ($query['decision'] ?? 'reject'),
        (string) ($query['comment'] ?? ''),
    );
} elseif ($path === '/audit/search') {
    $response = ['items' => $security->searchAudit($query)];
} elseif ($path === '/break-glass/activate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = $security->requestBreakGlass($context, (string) ($query['reason'] ?? 'incident response'), (int) ($query['ttlMinutes'] ?? 30));
} elseif ($path === '/locks/acquire' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = $security->acquireLock($context, (string) ($query['resourceType'] ?? 'mapping'), (string) ($query['resourceId'] ?? 'default'));
} elseif ($path === '/locks/handoff' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = $security->handoffLock(
        $context,
        (string) ($query['resourceType'] ?? 'mapping'),
        (string) ($query['resourceId'] ?? 'default'),
        (string) ($query['toActorId'] ?? ''),
    );
} elseif ($path === '/jobs/action' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $permission = match ((string) ($query['action'] ?? '')) {
        'rollback' => 'jobs.rollback.execute',
        'start', 'pause', 'resume', 'cancel', 'retry', 'verify' => 'jobs.operate',
        default => 'jobs.view',
    };

    $decision = $security->authorize($context, $permission, ['tenantId' => $context->tenantId, 'workspaceId' => $context->workspaceId, 'environment' => $context->environment], $permission === 'jobs.rollback.execute');

    if (!$decision->allowed) {
        http_response_code(403);
        $response = ['ok' => false, 'error' => 'forbidden', 'decision' => $decision->toArray()];
    } elseif ($decision->approvalRequired) {
        $validation = $security->validateApprovalToken(
            (string) ($query['approvalId'] ?? ''),
            (string) ($query['approvalToken'] ?? ''),
            'job.' . (string) ($query['action'] ?? 'unknown'),
            (array) ($query['payload'] ?? []),
        );

        if (($validation['valid'] ?? false) !== true) {
            http_response_code(422);
            $response = ['ok' => false, 'error' => 'approval_invalid', 'validation' => $validation, 'decision' => $decision->toArray()];
        } else {
            $response = ['ok' => true, 'jobId' => (string) ($query['jobId'] ?? ''), 'action' => (string) ($query['action'] ?? ''), 'approval' => $validation, 'decision' => $decision->toArray(), 'acceptedAt' => date(DATE_ATOM)];
        }
    } else {
        $response = ['ok' => true, 'jobId' => (string) ($query['jobId'] ?? ''), 'action' => (string) ($query['action'] ?? ''), 'decision' => $decision->toArray(), 'acceptedAt' => date(DATE_ATOM)];
    }

    $security->appendAuditEvent([
        'actorId' => $context->actorId,
        'actorRoles' => $context->roles,
        'tenantId' => $context->tenantId,
        'workspaceId' => $context->workspaceId,
        'actionType' => 'job.action.' . (string) ($query['action'] ?? 'unknown'),
        'targetResourceType' => 'migration_job',
        'targetResourceId' => (string) ($query['jobId'] ?? 'latest'),
        'requestPayloadSnapshot' => $query,
        'policyDecision' => $decision->toArray(),
        'resultStatus' => (($response['ok'] ?? false) === true) ? 'accepted' : 'denied',
        'correlationId' => (string) ($query['correlationId'] ?? uniqid('corr-', true)),
        'traceId' => (string) ($query['traceId'] ?? uniqid('tr-', true)),
        'riskScore' => $decision->riskScore,
        'securityLabels' => $decision->approvalRequired ? ['approval-required'] : ['standard'],
    ]);
} elseif (in_array($path, ['/workers/action', '/mapping/action', '/integrity/action', '/conflicts/action', '/replay/action'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = [
        'ok' => true,
        'scope' => trim((string) preg_replace('#^/#', '', str_replace('/action', '', $path))),
        'jobId' => (string) ($query['jobId'] ?? ''),
        'action' => (string) ($query['action'] ?? 'noop'),
        'payload' => $query,
        'acceptedAt' => date(DATE_ATOM),
        'message' => 'Action contract accepted. Runtime binding can be enabled behind feature flag.',
    ];
} else {
    $response = match ($path) {
        '/dashboard' => $api->dashboard($query['jobId'] ?? null),
        '/meta' => $api->meta(),
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
