<?php

declare(strict_types=1);

return [
    'max_requests_per_second' => (int) ($_ENV['RUNTIME_MAX_RPS'] ?? 10),
    'max_concurrent_workers' => (int) ($_ENV['RUNTIME_MAX_WORKERS'] ?? 2),
    'adaptive_slowdown' => (bool) ($_ENV['RUNTIME_ADAPTIVE_SLOWDOWN'] ?? true),
    'timeouts' => [
        'request_seconds' => (int) ($_ENV['RUNTIME_REQUEST_TIMEOUT'] ?? 30),
    ],
];
