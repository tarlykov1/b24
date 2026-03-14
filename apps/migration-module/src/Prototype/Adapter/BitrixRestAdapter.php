<?php

declare(strict_types=1);

namespace MigrationModule\Prototype\Adapter;

use MigrationModule\Infrastructure\Bitrix\BitrixRestClient;

final class BitrixRestAdapter implements SourceAdapterInterface, TargetAdapterInterface
{
    /** @var array<string,string> */
    private array $sourceMethods = [
        'crm.deal' => 'crm.deal.list',
        'crm.lead' => 'crm.lead.list',
        'crm.contact' => 'crm.contact.list',
        'crm.company' => 'crm.company.list',
        'tasks.task' => 'tasks.task.list',
        'user' => 'user.get',
        'disk.file' => 'disk.file.get',
    ];

    /** @var array<string,string> */
    private array $upsertMethods = [
        'crm.deal' => 'crm.deal.update',
        'crm.lead' => 'crm.lead.update',
        'crm.contact' => 'crm.contact.update',
        'crm.company' => 'crm.company.update',
        'tasks.task' => 'tasks.task.update',
        'user' => 'user.update',
    ];

    public function __construct(private readonly BitrixRestClient $client, private readonly string $incrementalField = '>DATE_MODIFY')
    {
    }

    public function fetch(string $entityType, int $offset, int $limit): array
    {
        $method = $this->sourceMethods[$entityType] ?? null;
        if ($method === null) {
            return [];
        }

        $filter = [];
        if (isset($_ENV['BITRIX_INCREMENTAL_FROM']) && $_ENV['BITRIX_INCREMENTAL_FROM'] !== '') {
            $filter[$this->incrementalField] = $_ENV['BITRIX_INCREMENTAL_FROM'];
        }

        $result = $this->client->list($method, $filter, $offset, $limit);

        return array_slice($result, 0, $limit);
    }

    public function entityTypes(): array
    {
        return array_keys($this->sourceMethods);
    }

    public function upsert(string $entityType, array $entity, bool $dryRun): array
    {
        if ($dryRun) {
            return ['target_id' => (string) ($entity['id'] ?? ''), 'status' => 'dry-run'];
        }

        $id = (string) ($entity['id'] ?? '');
        $method = $this->upsertMethods[$entityType] ?? null;
        if ($method === null || $id === '') {
            return ['target_id' => $id, 'status' => 'unsupported'];
        }

        $fields = $entity;
        unset($fields['id']);

        $response = $this->client->call($method, ['id' => $id, 'fields' => $fields]);

        return ['target_id' => $id, 'status' => ((bool) ($response['result'] ?? true)) ? 'updated' : 'partial_failure'];
    }

    public function exists(string $entityType, string $targetId): bool
    {
        $method = $this->sourceMethods[$entityType] ?? null;
        if ($method === null || $targetId === '') {
            return false;
        }

        $result = $this->client->list($method, ['ID' => $targetId], 0, 1);

        return count($result) > 0;
    }
}
