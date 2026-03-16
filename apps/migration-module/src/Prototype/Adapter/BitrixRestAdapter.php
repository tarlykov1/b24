<?php

declare(strict_types=1);

namespace MigrationModule\Prototype\Adapter;

use MigrationModule\Infrastructure\Bitrix\BitrixRestClient;

final class BitrixRestAdapter implements SourceAdapterInterface, TargetAdapterInterface
{
    /** @var array<string,array{list:string,read:string,write?:string,deltaField?:string,relationships:list<string>,capabilities:array<string,bool>}> */
    private array $entityMap = [
        'users' => [
            'list' => 'user.get',
            'read' => 'user.get',
            'write' => 'user.update',
            'deltaField' => '>TIMESTAMP_X',
            'relationships' => ['departments', 'groups/projects', 'tasks', 'disk'],
            'capabilities' => ['readSupported' => true, 'writeSupported' => true, 'deltaSupported' => true, 'attachmentSupported' => false, 'idPreserveSupported' => true],
        ],
        'departments' => [
            'list' => 'department.get',
            'read' => 'department.get',
            'deltaField' => '>TIMESTAMP_X',
            'relationships' => ['users'],
            'capabilities' => ['readSupported' => true, 'writeSupported' => false, 'deltaSupported' => true, 'attachmentSupported' => false, 'idPreserveSupported' => true],
        ],
        'groups/projects' => [
            'list' => 'sonet_group.get',
            'read' => 'sonet_group.get',
            'deltaField' => '>DATE_MODIFY',
            'relationships' => ['users', 'tasks', 'disk'],
            'capabilities' => ['readSupported' => true, 'writeSupported' => false, 'deltaSupported' => true, 'attachmentSupported' => true, 'idPreserveSupported' => true],
        ],
        'crm.contact' => [
            'list' => 'crm.contact.list',
            'read' => 'crm.contact.get',
            'write' => 'crm.contact.update',
            'deltaField' => '>DATE_MODIFY',
            'relationships' => ['crm.company', 'crm.deal'],
            'capabilities' => ['readSupported' => true, 'writeSupported' => true, 'deltaSupported' => true, 'attachmentSupported' => true, 'idPreserveSupported' => true],
        ],
        'crm.company' => [
            'list' => 'crm.company.list',
            'read' => 'crm.company.get',
            'write' => 'crm.company.update',
            'deltaField' => '>DATE_MODIFY',
            'relationships' => ['crm.contact', 'crm.deal'],
            'capabilities' => ['readSupported' => true, 'writeSupported' => true, 'deltaSupported' => true, 'attachmentSupported' => true, 'idPreserveSupported' => true],
        ],
        'crm.deal' => [
            'list' => 'crm.deal.list',
            'read' => 'crm.deal.get',
            'write' => 'crm.deal.update',
            'deltaField' => '>DATE_MODIFY',
            'relationships' => ['crm.contact', 'crm.company', 'users'],
            'capabilities' => ['readSupported' => true, 'writeSupported' => true, 'deltaSupported' => true, 'attachmentSupported' => true, 'idPreserveSupported' => true],
        ],
        'crm.lead' => [
            'list' => 'crm.lead.list',
            'read' => 'crm.lead.get',
            'write' => 'crm.lead.update',
            'deltaField' => '>DATE_MODIFY',
            'relationships' => ['users'],
            'capabilities' => ['readSupported' => true, 'writeSupported' => true, 'deltaSupported' => true, 'attachmentSupported' => true, 'idPreserveSupported' => true],
        ],
        'smart_processes' => [
            'list' => 'crm.item.list',
            'read' => 'crm.item.get',
            'write' => 'crm.item.update',
            'deltaField' => '>UPDATED_TIME',
            'relationships' => ['users', 'files'],
            'capabilities' => ['readSupported' => true, 'writeSupported' => true, 'deltaSupported' => true, 'attachmentSupported' => true, 'idPreserveSupported' => true],
        ],
        'tasks.task' => [
            'list' => 'tasks.task.list',
            'read' => 'tasks.task.get',
            'write' => 'tasks.task.update',
            'deltaField' => '>CHANGED_DATE',
            'relationships' => ['users', 'groups/projects', 'task.comments', 'task.checklists', 'files'],
            'capabilities' => ['readSupported' => true, 'writeSupported' => true, 'deltaSupported' => true, 'attachmentSupported' => true, 'idPreserveSupported' => true],
        ],
        'task.comments' => [
            'list' => 'task.commentitem.getlist',
            'read' => 'task.commentitem.get',
            'write' => 'task.commentitem.update',
            'deltaField' => '>POST_DATE',
            'relationships' => ['tasks.task', 'users', 'files'],
            'capabilities' => ['readSupported' => true, 'writeSupported' => true, 'deltaSupported' => true, 'attachmentSupported' => true, 'idPreserveSupported' => false],
        ],
        'task.checklists' => [
            'list' => 'task.checklistitem.getlist',
            'read' => 'task.checklistitem.get',
            'write' => 'task.checklistitem.update',
            'deltaField' => '>CHANGED_DATE',
            'relationships' => ['tasks.task', 'users'],
            'capabilities' => ['readSupported' => true, 'writeSupported' => true, 'deltaSupported' => true, 'attachmentSupported' => false, 'idPreserveSupported' => false],
        ],
        'files' => [
            'list' => 'disk.file.getlist',
            'read' => 'disk.file.get',
            'deltaField' => '>UPDATE_TIME',
            'relationships' => ['disk', 'users', 'tasks.task', 'task.comments'],
            'capabilities' => ['readSupported' => true, 'writeSupported' => false, 'deltaSupported' => true, 'attachmentSupported' => true, 'idPreserveSupported' => true],
        ],
        'disk' => [
            'list' => 'disk.storage.getlist',
            'read' => 'disk.storage.get',
            'deltaField' => '>UPDATE_TIME',
            'relationships' => ['users', 'groups/projects', 'files'],
            'capabilities' => ['readSupported' => true, 'writeSupported' => false, 'deltaSupported' => true, 'attachmentSupported' => true, 'idPreserveSupported' => true],
        ],
    ];

    /** @var array<string,string> */
    private array $compatibilityAliases = [
        'user' => 'users',
        'disk.file' => 'files',
    ];

    public function __construct(private readonly BitrixRestClient $client)
    {
    }

    public function fetch(string $entityType, int $offset, int $limit): array
    {
        return $this->list($entityType, $offset, $limit);
    }

    /** @return list<string> */
    public function entityTypes(): array
    {
        return array_values(array_unique(array_merge(array_keys($this->entityMap), array_keys($this->compatibilityAliases))));
    }

    /** @return array<int,array<string,mixed>> */
    public function list(string $entityType, int $offset = 0, int $limit = 50, array $filter = []): array
    {
        $resolved = $this->resolveEntityType($entityType);
        $mapping = $this->entityMap[$resolved] ?? null;
        if ($mapping === null) {
            return [];
        }

        $effectiveFilter = $this->withIncrementalFilter($resolved, $filter);
        $result = $this->client->list($mapping['list'], $effectiveFilter, $offset, $limit);

        return array_slice($result, 0, $limit);
    }

    /** @return array<string,mixed> */
    public function read(string $entityType, string $id): array
    {
        $resolved = $this->resolveEntityType($entityType);
        $mapping = $this->entityMap[$resolved] ?? null;
        if ($mapping === null || $id === '') {
            return [];
        }

        $response = $this->client->call($mapping['read'], ['id' => $id]);
        if (isset($response['item']) && is_array($response['item'])) {
            return $response['item'];
        }

        return $response;
    }

    /** @param list<string> $ids @return array<string,array<string,mixed>> */
    public function batchRead(string $entityType, array $ids): array
    {
        $resolved = $this->resolveEntityType($entityType);
        $mapping = $this->entityMap[$resolved] ?? null;
        if ($mapping === null || $ids === []) {
            return [];
        }

        $commands = [];
        foreach ($ids as $id) {
            if ($id === '') {
                continue;
            }

            $commands[] = ['method' => $mapping['read'], 'params' => ['id' => $id]];
        }

        $result = [];
        foreach (array_chunk($commands, 50) as $chunk) {
            $batch = $this->client->batch($chunk);
            foreach ($chunk as $index => $command) {
                $id = (string) ($command['params']['id'] ?? '');
                $payload = $batch['result']['result']['cmd_' . $index] ?? $batch['result']['cmd_' . $index] ?? $batch['cmd_' . $index] ?? [];
                $result[$id] = is_array($payload) ? $payload : ['value' => $payload];
            }
        }

        return $result;
    }

    /** @return array<int,array<string,mixed>> */
    public function delta(string $entityType, string $since, int $offset = 0, int $limit = 50): array
    {
        $resolved = $this->resolveEntityType($entityType);
        $mapping = $this->entityMap[$resolved] ?? null;
        if ($mapping === null || $since === '') {
            return [];
        }

        $deltaField = $mapping['deltaField'] ?? '>DATE_MODIFY';

        return $this->list($resolved, $offset, $limit, [$deltaField => $since]);
    }

    /** @return list<string> */
    public function relationships(string $entityType): array
    {
        $resolved = $this->resolveEntityType($entityType);

        return $this->entityMap[$resolved]['relationships'] ?? [];
    }

    /** @return array<string,bool> */
    public function capabilities(string $entityType): array
    {
        $resolved = $this->resolveEntityType($entityType);

        return $this->entityMap[$resolved]['capabilities'] ?? [
            'readSupported' => false,
            'writeSupported' => false,
            'deltaSupported' => false,
            'attachmentSupported' => false,
            'idPreserveSupported' => false,
        ];
    }

    public function upsert(string $entityType, array $entity, bool $dryRun): array
    {
        if ($dryRun) {
            return ['target_id' => (string) ($entity['id'] ?? ''), 'status' => 'dry-run'];
        }

        $resolved = $this->resolveEntityType($entityType);
        $mapping = $this->entityMap[$resolved] ?? null;
        $id = (string) ($entity['id'] ?? '');

        if ($mapping === null || !isset($mapping['write']) || $id === '') {
            return ['target_id' => $id, 'status' => 'unsupported'];
        }

        $fields = $entity;
        unset($fields['id']);

        $response = $this->client->call($mapping['write'], ['id' => $id, 'fields' => $fields]);

        return ['target_id' => $id, 'status' => ((bool) ($response['result'] ?? true)) ? 'updated' : 'partial_failure'];
    }


    public function apply(string $entityType, array $payload): array
    {
        return $this->upsert($entityType, $payload, false);
    }

    public function exists(string $entityType, string $targetId): bool
    {
        if ($targetId === '') {
            return false;
        }

        return $this->read($entityType, $targetId) !== [];
    }

    private function resolveEntityType(string $entityType): string
    {
        return $this->compatibilityAliases[$entityType] ?? $entityType;
    }

    /** @param array<string,mixed> $filter @return array<string,mixed> */
    private function withIncrementalFilter(string $entityType, array $filter): array
    {
        $since = isset($_ENV['BITRIX_INCREMENTAL_FROM']) && $_ENV['BITRIX_INCREMENTAL_FROM'] !== '' ? (string) $_ENV['BITRIX_INCREMENTAL_FROM'] : '';
        if ($since === '') {
            return $filter;
        }

        $deltaField = $this->entityMap[$entityType]['deltaField'] ?? '>DATE_MODIFY';
        if (!isset($filter[$deltaField])) {
            $filter[$deltaField] = $since;
        }

        return $filter;
    }
}
