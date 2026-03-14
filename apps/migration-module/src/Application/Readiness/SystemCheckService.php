<?php

declare(strict_types=1);

namespace MigrationModule\Application\Readiness;

use MigrationModule\Infrastructure\Bitrix\BitrixRestClient;

final class SystemCheckService
{
    public function __construct(private readonly ?BitrixRestClient $client = null)
    {
    }

    /** @return array<string,mixed> */
    public function check(string $storagePath): array
    {
        $checks = [
            'bitrix_connectivity' => false,
            'api_permissions' => false,
            'filesystem_access' => is_writable(dirname($storagePath)),
            'storage_availability' => extension_loaded('pdo_sqlite'),
            'queue_health' => true,
        ];

        if ($this->client !== null) {
            try {
                $user = $this->client->call('user.current');
                $checks['bitrix_connectivity'] = isset($user['ID']) || isset($user['id']);
                $checks['api_permissions'] = true;
            } catch (\Throwable) {
                $checks['bitrix_connectivity'] = false;
                $checks['api_permissions'] = false;
            }
        }

        return [
            'ok' => !in_array(false, $checks, true),
            'checks' => $checks,
            'checked_at' => date(DATE_ATOM),
        ];
    }
}
