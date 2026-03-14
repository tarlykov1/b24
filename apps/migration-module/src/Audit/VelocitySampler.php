<?php

declare(strict_types=1);

namespace MigrationModule\Audit;

use DateInterval;
use DateTimeImmutable;
use MigrationModule\Infrastructure\Bitrix\BitrixRestClient;
use PDO;
use Throwable;

final class VelocitySampler
{
    /** @var array<string,array{table:string,id:string,modified:string,created:?string,deleted:?string,rest:?string,fs_paths:list<string>,logs:list<array{table:string,column:string}>}> */
    private array $entityMap = [
        'users' => ['table' => 'b_user', 'id' => 'ID', 'modified' => 'TIMESTAMP_X', 'created' => 'DATE_REGISTER', 'deleted' => null, 'rest' => 'user.get', 'fs_paths' => [], 'logs' => [['table' => 'b_event_log', 'column' => 'AUDIT_TYPE_ID']]],
        'crm_leads' => ['table' => 'b_crm_lead', 'id' => 'ID', 'modified' => 'DATE_MODIFY', 'created' => 'DATE_CREATE', 'deleted' => null, 'rest' => 'crm.lead.list', 'fs_paths' => ['crm'], 'logs' => [['table' => 'b_crm_activity', 'column' => 'OWNER_TYPE_ID']]],
        'crm_deals' => ['table' => 'b_crm_deal', 'id' => 'ID', 'modified' => 'DATE_MODIFY', 'created' => 'DATE_CREATE', 'deleted' => null, 'rest' => 'crm.deal.list', 'fs_paths' => ['crm'], 'logs' => [['table' => 'b_crm_activity', 'column' => 'OWNER_TYPE_ID']]],
        'crm_contacts' => ['table' => 'b_crm_contact', 'id' => 'ID', 'modified' => 'DATE_MODIFY', 'created' => 'DATE_CREATE', 'deleted' => null, 'rest' => 'crm.contact.list', 'fs_paths' => ['crm'], 'logs' => [['table' => 'b_crm_activity', 'column' => 'OWNER_TYPE_ID']]],
        'crm_companies' => ['table' => 'b_crm_company', 'id' => 'ID', 'modified' => 'DATE_MODIFY', 'created' => 'DATE_CREATE', 'deleted' => null, 'rest' => 'crm.company.list', 'fs_paths' => ['crm'], 'logs' => [['table' => 'b_crm_activity', 'column' => 'OWNER_TYPE_ID']]],
        'crm_activities' => ['table' => 'b_crm_activity', 'id' => 'ID', 'modified' => 'LAST_UPDATED', 'created' => 'CREATED', 'deleted' => null, 'rest' => 'crm.activity.list', 'fs_paths' => ['crm'], 'logs' => []],
        'tasks' => ['table' => 'b_tasks', 'id' => 'ID', 'modified' => 'CHANGED_DATE', 'created' => 'CREATED_DATE', 'deleted' => 'ZOMBIE', 'rest' => 'tasks.task.list', 'fs_paths' => ['tasks'], 'logs' => [['table' => 'b_task_log', 'column' => 'CREATED_DATE']]],
        'comments' => ['table' => 'b_forum_message', 'id' => 'ID', 'modified' => 'POST_DATE', 'created' => 'POST_DATE', 'deleted' => null, 'rest' => 'forum.message.list', 'fs_paths' => ['tasks', 'crm'], 'logs' => [['table' => 'b_log', 'column' => 'LOG_DATE']]],
        'files' => ['table' => 'b_file', 'id' => 'ID', 'modified' => 'TIMESTAMP_X', 'created' => 'TIMESTAMP_X', 'deleted' => null, 'rest' => 'disk.storage.getchildren', 'fs_paths' => ['upload'], 'logs' => []],
        'smart_processes' => ['table' => 'b_crm_dynamic_items', 'id' => 'ID', 'modified' => 'UPDATED_TIME', 'created' => 'CREATED_TIME', 'deleted' => null, 'rest' => 'crm.item.list', 'fs_paths' => ['crm'], 'logs' => [['table' => 'b_log', 'column' => 'LOG_DATE']]],
        'calendar_events' => ['table' => 'b_calendar_event', 'id' => 'ID', 'modified' => 'TIMESTAMP_X', 'created' => 'DATE_CREATE', 'deleted' => null, 'rest' => 'calendar.event.get', 'fs_paths' => [], 'logs' => []],
        'groups_projects' => ['table' => 'b_sonet_group', 'id' => 'ID', 'modified' => 'DATE_MODIFY', 'created' => 'DATE_CREATE', 'deleted' => null, 'rest' => 'sonet_group.get', 'fs_paths' => [], 'logs' => []],
        'disk_files' => ['table' => 'b_disk_object', 'id' => 'ID', 'modified' => 'UPDATE_TIME', 'created' => 'CREATE_TIME', 'deleted' => 'DELETED_TYPE', 'rest' => 'disk.file.get', 'fs_paths' => ['disk'], 'logs' => []],
        'workflows' => ['table' => 'b_bp_workflow_instance', 'id' => 'ID', 'modified' => 'MODIFIED', 'created' => 'STARTED', 'deleted' => null, 'rest' => 'bizproc.workflow.instances', 'fs_paths' => [], 'logs' => [['table' => 'b_log', 'column' => 'LOG_DATE']]],
        'automation_logs' => ['table' => 'b_bp_tracking', 'id' => 'ID', 'modified' => 'MODIFIED', 'created' => 'MODIFIED', 'deleted' => null, 'rest' => 'bizproc.event.list', 'fs_paths' => [], 'logs' => [['table' => 'b_bp_tracking', 'column' => 'MODIFIED']]],
    ];

    /** @return array{entities:array<string,array<string,mixed>>,velocity_heatmap:array<string,int>,sources:array<string,mixed>} */
    public function sample(?PDO $pdo, ?BitrixRestClient $restClient, string $uploadRoot, int $days, int $sampleSize, ?string $entity = null): array
    {
        $entities = [];
        $heatmap = array_fill_keys(range(0, 23), 0);

        foreach ($this->entityMap as $entityKey => $definition) {
            if ($entity !== null && $entity !== $entityKey) {
                continue;
            }

            $metrics = $this->fromDatabase($pdo, $definition, $days);
            $metrics['rest_samples'] = $this->fromRest($restClient, (string) ($definition['rest'] ?? ''), $sampleSize);
            $metrics['filesystem_samples'] = $this->fromFilesystem($uploadRoot, $definition['fs_paths'], $days, $sampleSize);
            $entities[$entityKey] = $metrics;

            foreach (($metrics['hourly_distribution'] ?? []) as $hour => $count) {
                $heatmap[(int) $hour] += (int) $count;
            }
        }

        return [
            'entities' => $entities,
            'velocity_heatmap' => array_combine(array_map(static fn (int $h): string => sprintf('%02d:00', $h), array_keys($heatmap)), array_values($heatmap)),
            'sources' => ['db' => $pdo !== null, 'rest' => $restClient !== null, 'filesystem' => is_dir($uploadRoot)],
        ];
    }

    /** @param array<string,mixed> $definition @return array<string,mixed> */
    private function fromDatabase(?PDO $pdo, array $definition, int $days): array
    {
        $default = [
            'total_entities' => 0,
            'changes_last_24h' => 0,
            'changes_last_7d' => 0,
            'changes_last_30d' => 0,
            'avg_changes_per_hour' => 0.0,
            'peak_changes_per_hour' => 0,
            'avg_entity_mutations' => 0.0,
            'new_entities_per_day' => 0.0,
            'updates_per_day' => 0.0,
            'deletions_per_day' => 0.0,
            'hourly_distribution' => [],
        ];
        if ($pdo === null) {
            return $default;
        }

        $table = $definition['table'];
        $id = $definition['id'];
        $modified = $definition['modified'];
        $created = $definition['created'];
        $deleted = $definition['deleted'];

        if (!$this->tableExists($pdo, $table) || !$this->columnExists($pdo, $table, $id)) {
            return $default;
        }

        $total = (int) ($this->fetchValue($pdo, "SELECT COUNT(*) FROM {$table}") ?? 0);
        $default['total_entities'] = $total;

        if ($this->columnExists($pdo, $table, $modified)) {
            $default['changes_last_24h'] = (int) ($this->fetchValue($pdo, "SELECT COUNT(*) FROM {$table} WHERE {$modified} >= :from", ['from' => $this->ago('P1D')]) ?? 0);
            $default['changes_last_7d'] = (int) ($this->fetchValue($pdo, "SELECT COUNT(*) FROM {$table} WHERE {$modified} >= :from", ['from' => $this->ago('P7D')]) ?? 0);
            $default['changes_last_30d'] = (int) ($this->fetchValue($pdo, "SELECT COUNT(*) FROM {$table} WHERE {$modified} >= :from", ['from' => $this->ago('P30D')]) ?? 0);
            $rows = $this->fetchRows(
                $pdo,
                "SELECT strftime('%H', {$modified}) AS hour_slot, COUNT(*) AS total FROM {$table} WHERE {$modified} >= :from GROUP BY strftime('%H', {$modified})",
                ['from' => $this->ago(sprintf('P%dD', max($days, 1)))],
            );
            foreach ($rows as $row) {
                if (($row['hour_slot'] ?? '') !== null) {
                    $default['hourly_distribution'][(int) $row['hour_slot']] = (int) ($row['total'] ?? 0);
                }
            }
        }

        $default['avg_changes_per_hour'] = round(((int) $default['changes_last_30d']) / max(30 * 24, 1), 2);
        $default['peak_changes_per_hour'] = count($default['hourly_distribution']) > 0 ? max($default['hourly_distribution']) : 0;

        if ($created !== null && $this->columnExists($pdo, $table, $created)) {
            $default['new_entities_per_day'] = round(((int) ($this->fetchValue($pdo, "SELECT COUNT(*) FROM {$table} WHERE {$created} >= :from", ['from' => $this->ago(sprintf('P%dD', max($days, 1)))]) ?? 0)) / max($days, 1), 2);
        }

        if ($this->columnExists($pdo, $table, $modified) && $created !== null && $this->columnExists($pdo, $table, $created)) {
            $updates = (int) ($this->fetchValue($pdo, "SELECT COUNT(*) FROM {$table} WHERE {$modified} >= :from AND ({$created} IS NULL OR {$modified} > {$created})", ['from' => $this->ago(sprintf('P%dD', max($days, 1)))]) ?? 0);
            $default['updates_per_day'] = round($updates / max($days, 1), 2);
        } else {
            $default['updates_per_day'] = round(((int) $default['changes_last_30d']) / 30, 2);
        }

        if ($deleted !== null && $this->columnExists($pdo, $table, $deleted)) {
            $deletedCount = (int) ($this->fetchValue($pdo, "SELECT COUNT(*) FROM {$table} WHERE {$deleted} IS NOT NULL") ?? 0);
            $default['deletions_per_day'] = round($deletedCount / max($days, 1), 2);
        }

        $logMutations = 0;
        foreach ((array) ($definition['logs'] ?? []) as $logDefinition) {
            if ($this->tableExists($pdo, $logDefinition['table'])) {
                $column = $logDefinition['column'];
                if ($this->columnExists($pdo, $logDefinition['table'], $column)) {
                    $logMutations += (int) ($this->fetchValue($pdo, "SELECT COUNT(*) FROM {$logDefinition['table']} WHERE {$column} >= :from", ['from' => $this->ago(sprintf('P%dD', max($days, 1)))]) ?? 0);
                }
            }
        }

        $default['avg_entity_mutations'] = $total > 0 ? round((((int) $default['changes_last_30d']) + $logMutations) / $total, 4) : 0.0;

        return $default;
    }

    /** @param list<string> $paths @return array<string,mixed> */
    private function fromFilesystem(string $uploadRoot, array $paths, int $days, int $sampleSize): array
    {
        $sampled = 0;
        $changed = 0;
        $cutoff = strtotime($this->ago(sprintf('P%dD', max($days, 1))));

        foreach ($paths as $path) {
            $root = rtrim($uploadRoot, '/') . '/' . ltrim($path, '/');
            if (!is_dir($root)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                if ($sampled >= $sampleSize) {
                    break 2;
                }

                $sampled++;
                if ($file->getMTime() >= $cutoff) {
                    $changed++;
                }
            }
        }

        return ['sampled_files' => $sampled, 'changed_in_window' => $changed, 'change_ratio' => $sampled > 0 ? round($changed / $sampled, 4) : 0.0];
    }

    /** @return array<string,mixed> */
    private function fromRest(?BitrixRestClient $restClient, string $method, int $sampleSize): array
    {
        if ($restClient === null || $method === '') {
            return ['sampled' => 0, 'changed_recently' => 0, 'change_ratio' => 0.0];
        }

        try {
            $items = $restClient->list($method, [], 0, max(min($sampleSize, 50), 1));
            $slice = array_slice($items, 0, $sampleSize);
            $threshold = strtotime($this->ago('P1D'));
            $changed = 0;

            foreach ($slice as $item) {
                $candidate = (string) ($item['DATE_MODIFY'] ?? $item['UPDATED_DATE'] ?? $item['TIMESTAMP_X'] ?? $item['CHANGED_DATE'] ?? '');
                if ($candidate !== '' && strtotime($candidate) >= $threshold) {
                    $changed++;
                }
            }

            return ['sampled' => count($slice), 'changed_recently' => $changed, 'change_ratio' => count($slice) > 0 ? round($changed / count($slice), 4) : 0.0];
        } catch (Throwable) {
            return ['sampled' => 0, 'changed_recently' => 0, 'change_ratio' => 0.0];
        }
    }

    /** @param array<string,mixed> $params */
    private function fetchValue(PDO $pdo, string $sql, array $params = []): mixed
    {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchColumn();
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<string,mixed> $params @return list<array<string,mixed>> */
    private function fetchRows(PDO $pdo, string $sql, array $params = []): array
    {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return is_array($rows) ? $rows : [];
        } catch (Throwable) {
            return [];
        }
    }

    private function ago(string $interval): string
    {
        return (new DateTimeImmutable('now'))->sub(new DateInterval($interval))->format('Y-m-d H:i:s');
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->query("SELECT 1 FROM {$table} LIMIT 1");

            return $stmt !== false;
        } catch (Throwable) {
            return false;
        }
    }

    private function columnExists(PDO $pdo, string $table, string $column): bool
    {
        try {
            $stmt = $pdo->query("SELECT * FROM {$table} LIMIT 1");
            if ($stmt === false) {
                return false;
            }
            for ($i = 0; $i < $stmt->columnCount(); $i++) {
                $meta = $stmt->getColumnMeta($i);
                if (strcasecmp((string) ($meta['name'] ?? ''), $column) === 0) {
                    return true;
                }
            }

            return false;
        } catch (Throwable) {
            return false;
        }
    }
}
