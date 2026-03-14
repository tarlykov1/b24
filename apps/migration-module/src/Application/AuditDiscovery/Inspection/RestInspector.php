<?php

declare(strict_types=1);

namespace MigrationModule\Application\AuditDiscovery\Inspection;

use MigrationModule\Infrastructure\Bitrix\BitrixRestClient;
use Throwable;

final class RestInspector
{
    public function inspect(?BitrixRestClient $client): array
    {
        if ($client === null) {
            return ['available' => false, 'reason' => 'rest_unavailable'];
        }

        try {
            $users = $this->safeList($client, 'user.get');
            $deals = $this->safeList($client, 'crm.deal.list');
            $contacts = $this->safeList($client, 'crm.contact.list');
            $companies = $this->safeList($client, 'crm.company.list');
            $tasks = $this->safeList($client, 'tasks.task.list');
            $storages = $this->safeCall($client, 'disk.storage.getlist');

            return [
                'available' => true,
                'users_total' => count($users),
                'crm_deals_total' => count($deals),
                'crm_contacts_total' => count($contacts),
                'crm_companies_total' => count($companies),
                'tasks_total' => count($tasks),
                'storages_total' => count((array) ($storages['items'] ?? $storages)),
                'custom_fields' => [
                    'deal' => $this->safeCall($client, 'crm.deal.fields'),
                    'contact' => $this->safeCall($client, 'crm.contact.fields'),
                    'company' => $this->safeCall($client, 'crm.company.fields'),
                ],
                'smart_processes' => (array) ($this->safeCall($client, 'crm.type.list')['types'] ?? []),
            ];
        } catch (Throwable $e) {
            return ['available' => false, 'reason' => 'rest_error', 'error' => $e->getMessage()];
        }
    }

    private function safeList(BitrixRestClient $client, string $method): array
    {
        try {
            return $client->list($method);
        } catch (Throwable) {
            return [];
        }
    }

    private function safeCall(BitrixRestClient $client, string $method): array
    {
        try {
            return $client->call($method);
        } catch (Throwable) {
            return [];
        }
    }
}
