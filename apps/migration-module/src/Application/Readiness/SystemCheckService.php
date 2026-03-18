<?php

declare(strict_types=1);

namespace MigrationModule\Application\Readiness;

use MigrationModule\Installation\ConnectivityProbeService;

final class SystemCheckService
{
    public function __construct(private readonly ConnectivityProbeService $probe = new ConnectivityProbeService())
    {
    }

    /** @param array<string,mixed> $dbConfig
     * @return array<string,mixed>
     */
    public function check(array $dbConfig = []): array
    {
        $result = $this->probe->probeMySql($dbConfig);

        $checks = [
            'pdo_mysql' => ($result['error']['code'] ?? null) !== 'pdo_mysql_missing',
            'mysql_tcp_reachable' => (bool) ($result['checks']['tcp'] ?? false),
            'mysql_select_1' => (bool) ($result['checks']['auth'] ?? false),
            'mysql_schema_permissions' => (bool) ($result['checks']['schema'] ?? false),
            'mysql_write_permissions' => (bool) ($result['checks']['write'] ?? false),
        ];

        return [
            'ok' => (bool) ($result['ok'] ?? false),
            'status' => (string) ($result['status'] ?? 'fail'),
            'code' => (bool) ($result['ok'] ?? false) ? 'system_check_passed' : 'system_check_failed',
            'checks' => $checks,
            'errors' => isset($result['error']) ? [$result['error']] : [],
            'checked_at' => (string) ($result['checked_at'] ?? date(DATE_ATOM)),
        ];
    }
}
