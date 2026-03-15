<?php

declare(strict_types=1);

namespace MigrationModule\Application\Consistency;

final class ConsistencyEngine
{
    /** @param array<string,array<int,array<string,mixed>>> $source @param array<string,array<int,array<string,mixed>>> $target */
    public function verifyCounts(array $source, array $target): array
    {
        $entities = array_values(array_unique(array_merge(array_keys($source), array_keys($target))));
        $items = [];
        $ok = true;

        foreach ($entities as $entityType) {
            $sourceCount = count($source[$entityType] ?? []);
            $targetCount = count($target[$entityType] ?? []);
            $diff = $targetCount - $sourceCount;
            $status = $diff === 0 ? 'OK' : 'MISMATCH';
            $ok = $ok && $status === 'OK';

            $items[] = [
                'entity_type' => $entityType,
                'source_count' => $sourceCount,
                'target_count' => $targetCount,
                'difference' => $diff,
                'status' => $status,
            ];
        }

        return ['check' => 'entity_counts', 'depth' => 'structural', 'healthy' => $ok, 'items' => $items];
    }

    /** @param array<string,array<int,array<string,mixed>>> $source @param array<string,array<int,array<string,mixed>>> $target */
    public function verifyFieldParity(array $source, array $target): array
    {
        $entities = array_values(array_unique(array_merge(array_keys($source), array_keys($target))));
        $items = [];
        $ok = true;

        foreach ($entities as $entityType) {
            $sourceFields = $this->collectFieldKeys($source[$entityType] ?? []);
            $targetFields = $this->collectFieldKeys($target[$entityType] ?? []);
            $missingInTarget = array_values(array_diff($sourceFields, $targetFields));
            $extraInTarget = array_values(array_diff($targetFields, $sourceFields));
            $status = ($missingInTarget === [] && $extraInTarget === []) ? 'OK' : 'MISMATCH';
            $ok = $ok && $status === 'OK';

            $items[] = [
                'entity_type' => $entityType,
                'source_fields' => $sourceFields,
                'target_fields' => $targetFields,
                'missing_in_target' => $missingInTarget,
                'extra_in_target' => $extraInTarget,
                'status' => $status,
            ];
        }

        return ['check' => 'field_parity', 'depth' => 'mapping', 'healthy' => $ok, 'items' => $items];
    }

    /** @param array<int,array<string,mixed>> $rows @return list<string> */
    private function collectFieldKeys(array $rows): array
    {
        $keys = [];
        foreach ($rows as $row) {
            foreach (array_keys($row) as $key) {
                $keys[] = (string) $key;
            }
        }

        $keys = array_values(array_unique($keys));
        sort($keys);

        return $keys;
    }

    /** @param array<int,array<string,mixed>> $relations */
    public function verifyRelationships(array $relations): array
    {
        return (new RelationIntegrityEngine())->verify($relations);
    }

    /** @param array<int,array<string,mixed>> $attachments */
    public function verifyAttachments(array $attachments): array
    {
        return (new FileReconciliationService())->verify($attachments);
    }

    /** @param array<int,array<string,mixed>> $sourceCustomFields @param array<int,array<string,mixed>> $targetCustomFields */
    public function verifyCustomFields(array $sourceCustomFields, array $targetCustomFields): array
    {
        $sourceMap = [];
        foreach ($sourceCustomFields as $field) {
            $sourceMap[(string) ($field['code'] ?? '')] = (string) ($field['type'] ?? 'unknown');
        }

        $targetMap = [];
        foreach ($targetCustomFields as $field) {
            $targetMap[(string) ($field['code'] ?? '')] = (string) ($field['type'] ?? 'unknown');
        }

        $issues = [];
        foreach ($sourceMap as $code => $type) {
            if (!array_key_exists($code, $targetMap)) {
                $issues[] = ['code' => $code, 'issue' => 'missing_in_target'];
                continue;
            }

            if ($targetMap[$code] !== $type) {
                $issues[] = ['code' => $code, 'issue' => 'type_mismatch', 'source_type' => $type, 'target_type' => $targetMap[$code]];
            }
        }

        return [
            'check' => 'custom_fields',
            'depth' => 'mapping',
            'source_total' => count($sourceMap),
            'target_total' => count($targetMap),
            'issues' => $issues,
            'healthy' => $issues === [],
        ];
    }

    /** @param array<int,array<string,mixed>> $sourceStages @param array<int,array<string,mixed>> $targetStages */
    public function verifyPipelineStages(array $sourceStages, array $targetStages): array
    {
        $sourceCodes = array_values(array_unique(array_map(static fn (array $stage): string => (string) ($stage['code'] ?? ''), $sourceStages)));
        $targetCodes = array_values(array_unique(array_map(static fn (array $stage): string => (string) ($stage['code'] ?? ''), $targetStages)));

        $missingInTarget = array_values(array_diff($sourceCodes, $targetCodes));

        return [
            'check' => 'pipeline_stages',
            'depth' => 'relation',
            'source_total' => count($sourceCodes),
            'target_total' => count($targetCodes),
            'missing_in_target' => $missingInTarget,
            'healthy' => $missingInTarget === [],
        ];
    }

    /** @param array<string,mixed> $dataset */
    public function verifyFull(array $dataset): array
    {
        $counts = $this->verifyCounts((array) ($dataset['source_entities'] ?? []), (array) ($dataset['target_entities'] ?? []));
        $fields = $this->verifyFieldParity((array) ($dataset['source_entities'] ?? []), (array) ($dataset['target_entities'] ?? []));
        $relations = $this->verifyRelationships((array) ($dataset['relations'] ?? []));
        $attachments = $this->verifyAttachments((array) ($dataset['attachments'] ?? []));
        $customFields = $this->verifyCustomFields((array) ($dataset['source_custom_fields'] ?? []), (array) ($dataset['target_custom_fields'] ?? []));
        $stages = $this->verifyPipelineStages((array) ($dataset['source_pipeline_stages'] ?? []), (array) ($dataset['target_pipeline_stages'] ?? []));

        return [
            'engine' => 'consistency',
            'verify_depth' => 'full',
            'checked' => ['entity_counts', 'field_parity', 'relationships', 'attachments', 'custom_fields', 'pipeline_stages'],
            'not_checked' => ['source_to_target_adapter_live_content_diff'],
            'limitations' => ['adapter_contract_depth_depends_on_runtime_adapter_availability'],
            'checks' => [
                'entity_counts' => $counts,
                'field_parity' => $fields,
                'relationships' => $relations,
                'attachments' => $attachments,
                'custom_fields' => $customFields,
                'pipeline_stages' => $stages,
            ],
            'healthy' => (bool) ($counts['healthy'] ?? false)
                && (bool) ($fields['healthy'] ?? false)
                && (bool) ($relations['healthy'] ?? false)
                && (bool) ($attachments['healthy'] ?? false)
                && (bool) ($customFields['healthy'] ?? false)
                && (bool) ($stages['healthy'] ?? false),
        ];
    }
}
