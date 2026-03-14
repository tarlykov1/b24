<?php

declare(strict_types=1);

namespace MigrationModule\ControlCenter\Service;

final class DiffEngine
{
    private const SUPPORTED_ENTITIES = ['users', 'crm_leads', 'crm_deals', 'crm_contacts', 'tasks', 'files'];

    /** @param array<string,mixed> $source @param array<string,mixed> $target
     * @return array<string,mixed>
     */
    public function compare(string $entity, string|int $id, array $source, array $target): array
    {
        if (!in_array($entity, self::SUPPORTED_ENTITIES, true)) {
            return ['entity' => $entity, 'id' => $id, 'diff' => [['field' => '*', 'source' => null, 'target' => null, 'status' => 'unsupported_entity']]];
        }

        $diff = [];
        $allFields = array_values(array_unique(array_merge(array_keys($source), array_keys($target))));

        foreach ($allFields as $field) {
            $sourceValue = $source[$field] ?? null;
            $targetValue = $target[$field] ?? null;

            if (!array_key_exists($field, $source) || !array_key_exists($field, $target)) {
                $status = 'missing_field';
            } elseif ($this->isRelationField($field) && $sourceValue !== $targetValue) {
                $status = 'missing_relation';
            } elseif ($this->isStageField($field) && $sourceValue !== $targetValue) {
                $status = 'mismatch';
            } elseif ($this->isUserMappingField($field) && $sourceValue !== $targetValue) {
                $status = 'user_mapping_difference';
            } elseif ($entity === 'files' && str_ends_with(strtolower($field), 'hash') && $sourceValue !== $targetValue) {
                $status = 'file_hash_mismatch';
            } elseif ($sourceValue !== $targetValue) {
                $status = 'changed';
            } else {
                $status = 'identical';
            }

            $diff[] = [
                'field' => (string) $field,
                'source' => $sourceValue,
                'target' => $targetValue,
                'status' => $status,
            ];
        }

        return ['entity' => $entity, 'id' => $id, 'diff' => $diff];
    }

    private function isRelationField(string $field): bool
    {
        return str_ends_with($field, '_id') || str_ends_with($field, '_ids');
    }

    private function isStageField(string $field): bool
    {
        return in_array(strtoupper($field), ['STAGE', 'STAGE_ID', 'STATUS'], true);
    }

    private function isUserMappingField(string $field): bool
    {
        return in_array(strtoupper($field), ['ASSIGNED_TO', 'ASSIGNED_BY_ID', 'RESPONSIBLE_ID', 'CREATED_BY'], true);
    }
}
