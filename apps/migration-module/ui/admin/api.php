<?php

declare(strict_types=1);

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
header('Access-Control-Allow-Headers: Content-Type, Authorization');
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

$api = new OperationsConsoleApi($pdo);
$query = array_merge($_GET, json_decode((string) file_get_contents('php://input'), true) ?? []);

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

if ($path === '/jobs/action' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'jobId' => (string) ($query['jobId'] ?? ''),
        'action' => (string) ($query['action'] ?? 'noop'),
        'acceptedAt' => date(DATE_ATOM),
        'message' => 'Action accepted via UI API contract. Runtime handler can be attached without UI changes.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$response = match ($path) {
    '/dashboard' => $api->dashboard($query['jobId'] ?? null),
    '/jobs' => $api->jobs($query),
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

header('Content-Type: application/json; charset=utf-8');
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
