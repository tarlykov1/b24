<?php

declare(strict_types=1);

namespace MigrationModule\Application\Mapping;

use MigrationModule\Infrastructure\Persistence\MigrationRepository;

final class AutoMappingService
{
    public function __construct(private readonly MigrationRepository $repository)
    {
    }

    /**
     * @param array<string,mixed> $sourceSchema
     * @param array<string,mixed> $targetSchema
     * @param array<string,mixed> $sampleData
     * @return array<string,mixed>
     */
    public function generate(string $jobId, array $sourceSchema, array $targetSchema, array $sampleData = []): array
    {
        $internal = [
            'source' => $this->buildInternalSchema($sourceSchema),
            'target' => $this->buildInternalSchema($targetSchema),
        ];

        $fieldMappings = $this->buildFieldMappings($internal['source'], $internal['target']);
        $stageMappings = $this->buildStageMappings($internal['source'], $internal['target']);
        $enumMappings = $this->buildEnumMappings($internal['source'], $internal['target']);
        $conflicts = $this->detectConflicts($internal['source'], $internal['target'], $fieldMappings, $stageMappings, $enumMappings);
        $dryRun = $this->dryRun($sampleData, $fieldMappings, $stageMappings, $enumMappings);

        $map = [
            'schema_model' => $internal,
            'field_mappings' => $fieldMappings,
            'stage_mappings' => $stageMappings,
            'enum_mappings' => $enumMappings,
            'conflicts' => $conflicts,
            'dry_run' => $dryRun,
            'summary' => [
                'field_coverage_percent' => $this->coverage($fieldMappings),
                'stage_coverage_percent' => $this->coverage($stageMappings),
                'enum_coverage_percent' => $this->coverage($enumMappings),
            ],
        ];

        $version = $this->repository->saveAutoMappingConfig($jobId, $map);
        $map['version'] = $version;

        return $map;
    }

    /** @param array<string,mixed> $schema @return array<string,mixed> */
    private function buildInternalSchema(array $schema): array
    {
        $entities = [];
        foreach (($schema['entities'] ?? []) as $entityCode => $entity) {
            $fields = [];
            foreach (($entity['fields'] ?? []) as $fieldCode => $field) {
                $fields[$fieldCode] = [
                    'code' => $fieldCode,
                    'name' => (string) ($field['name'] ?? $fieldCode),
                    'type' => (string) ($field['type'] ?? 'string'),
                    'required' => (bool) ($field['required'] ?? false),
                    'writable' => !((bool) ($field['read_only'] ?? false)),
                    'multiple' => (bool) ($field['multiple'] ?? false),
                    'custom' => str_starts_with($fieldCode, 'UF_'),
                    'enum_values' => array_values(array_map('strval', (array) ($field['enum_values'] ?? []))),
                ];
            }

            $entities[$entityCode] = [
                'code' => $entityCode,
                'name' => (string) ($entity['name'] ?? $entityCode),
                'type' => (string) ($entity['type'] ?? 'crm_entity'),
                'categories' => (array) ($entity['categories'] ?? []),
                'pipelines' => (array) ($entity['pipelines'] ?? []),
                'stages' => (array) ($entity['stages'] ?? []),
                'relations' => (array) ($entity['relations'] ?? []),
                'fields' => $fields,
            ];
        }

        return ['entities' => $entities];
    }

    /** @param array<string,mixed> $source @param array<string,mixed> $target @return array<int,array<string,mixed>> */
    private function buildFieldMappings(array $source, array $target): array
    {
        $mappings = [];

        foreach ($source['entities'] as $entityCode => $sourceEntity) {
            $targetEntity = $target['entities'][$entityCode] ?? null;
            if ($targetEntity === null) {
                continue;
            }

            foreach ($sourceEntity['fields'] as $sourceCode => $sourceField) {
                $best = null;
                foreach ($targetEntity['fields'] as $targetCode => $targetField) {
                    $score = 0;
                    $signals = [];

                    if ($sourceCode === $targetCode) {
                        $score += 50;
                        $signals[] = 'exact_system_code';
                    }
                    if ($this->normalize((string) $sourceField['name']) === $this->normalize((string) $targetField['name'])) {
                        $score += 20;
                        $signals[] = 'normalized_name';
                    }
                    if ((string) $sourceField['type'] === (string) $targetField['type']) {
                        $score += 15;
                        $signals[] = 'type_match';
                    }
                    if ((bool) $sourceField['multiple'] === (bool) $targetField['multiple']) {
                        $score += 5;
                        $signals[] = 'multiplicity_match';
                    }
                    if ($this->semanticKey((string) $sourceField['name']) === $this->semanticKey((string) $targetField['name'])) {
                        $score += 15;
                        $signals[] = 'semantic_match';
                    }

                    $historical = $this->repository->findHistoricalFieldMapping((string) $sourceCode, $entityCode);
                    if ($historical !== null && $historical === $targetCode) {
                        $score += 25;
                        $signals[] = 'historical_mapping';
                    }

                    if ($best === null || $score > $best['score']) {
                        $best = [
                            'entity' => $entityCode,
                            'source_field' => $sourceCode,
                            'target_field' => $targetCode,
                            'source_type' => $sourceField['type'],
                            'target_type' => $targetField['type'],
                            'score' => min($score, 100),
                            'signals' => $signals,
                            'transformation_rule' => $this->typeTransformation((string) $sourceField['type'], (string) $targetField['type']),
                            'status' => 'auto',
                        ];
                    }
                }

                if ($best === null) {
                    continue;
                }

                $best['confidence'] = $this->confidence((int) $best['score']);
                if ($best['confidence'] === 'low') {
                    $best['status'] = 'needs_review';
                }
                $best['explain'] = sprintf('Matched by %s', implode(', ', $best['signals']));
                $mappings[] = $best;
                $this->repository->rememberHistoricalFieldMapping($entityCode, (string) $best['source_field'], (string) $best['target_field']);
            }
        }

        return $mappings;
    }

    /** @param array<string,mixed> $source @param array<string,mixed> $target @return array<int,array<string,mixed>> */
    private function buildStageMappings(array $source, array $target): array
    {
        $out = [];
        foreach ($source['entities'] as $entityCode => $sourceEntity) {
            $targetEntity = $target['entities'][$entityCode] ?? null;
            if ($targetEntity === null) {
                continue;
            }

            $targetStages = (array) ($targetEntity['stages'] ?? []);
            foreach ((array) ($sourceEntity['stages'] ?? []) as $index => $stage) {
                $sourceName = (string) ($stage['name'] ?? '');
                $best = null;
                foreach ($targetStages as $tIndex => $targetStage) {
                    $score = 0;
                    $signals = [];
                    if ((string) ($stage['code'] ?? '') === (string) ($targetStage['code'] ?? '')) {
                        $score += 50;
                        $signals[] = 'exact_code';
                    }
                    if ($this->normalize($sourceName) === $this->normalize((string) ($targetStage['name'] ?? ''))) {
                        $score += 25;
                        $signals[] = 'name_match';
                    }
                    if ($index === $tIndex) {
                        $score += 10;
                        $signals[] = 'same_order';
                    }
                    if (($stage['group'] ?? null) === ($targetStage['group'] ?? null) && isset($stage['group'])) {
                        $score += 15;
                        $signals[] = 'status_group_match';
                    }
                    if ($best === null || $score > $best['score']) {
                        $best = [
                            'entity' => $entityCode,
                            'source_stage' => $stage,
                            'target_stage' => $targetStage,
                            'score' => min($score, 100),
                            'signals' => $signals,
                        ];
                    }
                }

                if ($best === null || $best['score'] < 35) {
                    $out[] = [
                        'entity' => $entityCode,
                        'source_stage' => $stage,
                        'target_stage' => null,
                        'score' => $best['score'] ?? 0,
                        'confidence' => 'low',
                        'status' => 'needs_creation',
                        'explain' => 'No close target stage found. Suggest creating stage/category.',
                    ];
                    continue;
                }

                $best['confidence'] = $this->confidence((int) $best['score']);
                $best['status'] = $best['confidence'] === 'high' ? 'auto' : 'needs_review';
                $best['explain'] = sprintf('Matched by %s', implode(', ', $best['signals']));
                $out[] = $best;
            }
        }

        return $out;
    }

    /** @param array<string,mixed> $source @param array<string,mixed> $target @return array<int,array<string,mixed>> */
    private function buildEnumMappings(array $source, array $target): array
    {
        $out = [];
        foreach ($source['entities'] as $entityCode => $sourceEntity) {
            $targetEntity = $target['entities'][$entityCode] ?? null;
            if ($targetEntity === null) {
                continue;
            }

            foreach ($sourceEntity['fields'] as $sourceCode => $sourceField) {
                if ((string) $sourceField['type'] !== 'enumeration') {
                    continue;
                }

                $targetField = $targetEntity['fields'][$sourceCode] ?? null;
                if ($targetField === null) {
                    continue;
                }

                $targetValues = array_map(fn ($v) => $this->normalize((string) $v), (array) $targetField['enum_values']);
                foreach ((array) $sourceField['enum_values'] as $value) {
                    $normalized = $this->normalize((string) $value);
                    $idx = array_search($normalized, $targetValues, true);
                    $status = $idx === false ? 'needs_creation' : 'auto';
                    $out[] = [
                        'entity' => $entityCode,
                        'field' => $sourceCode,
                        'source_value' => $value,
                        'target_value' => $idx === false ? 'unknown' : (array_values((array) $targetField['enum_values'])[$idx] ?? 'unknown'),
                        'status' => $status,
                        'confidence' => $idx === false ? 'low' : 'high',
                        'score' => $idx === false ? 20 : 90,
                        'explain' => $idx === false ? 'Fallback to unknown/other' : 'Normalized enum match',
                    ];
                }
            }
        }

        return $out;
    }

    /** @param array<string,mixed> $sampleData @param array<int,array<string,mixed>> $fieldMappings @param array<int,array<string,mixed>> $stageMappings @param array<int,array<string,mixed>> $enumMappings @return array<string,mixed> */
    private function dryRun(array $sampleData, array $fieldMappings, array $stageMappings, array $enumMappings): array
    {
        $errors = [];
        $warnings = [];

        foreach ($fieldMappings as $mapping) {
            if (($mapping['status'] ?? '') === 'needs_review') {
                $warnings[] = sprintf('%s.%s requires manual review', $mapping['entity'], $mapping['source_field']);
            }
            if (($mapping['transformation_rule'] ?? '') === 'incompatible') {
                $errors[] = sprintf('%s.%s has incompatible type', $mapping['entity'], $mapping['source_field']);
            }
        }

        foreach ($stageMappings as $stageMapping) {
            if (($stageMapping['status'] ?? '') === 'needs_creation') {
                $warnings[] = sprintf('%s missing stage %s', $stageMapping['entity'], (string) ($stageMapping['source_stage']['name'] ?? 'unknown'));
            }
        }

        $coveredFields = count(array_filter($fieldMappings, fn ($m) => ($m['status'] ?? '') === 'auto'));
        $coveredStages = count(array_filter($stageMappings, fn ($m) => ($m['status'] ?? '') === 'auto'));
        $coveredEnums = count(array_filter($enumMappings, fn ($m) => ($m['status'] ?? '') === 'auto'));

        return [
            'sample_size' => max(1, count($sampleData)),
            'preview' => array_slice($sampleData, 0, 3),
            'errors' => $errors,
            'warnings' => $warnings,
            'coverage' => [
                'fields_percent' => $this->percent($coveredFields, max(1, count($fieldMappings))),
                'stages_percent' => $this->percent($coveredStages, max(1, count($stageMappings))),
                'enums_percent' => $this->percent($coveredEnums, max(1, count($enumMappings))),
            ],
        ];
    }

    /** @param array<string,mixed> $source @param array<string,mixed> $target @param array<int,array<string,mixed>> $fieldMappings @param array<int,array<string,mixed>> $stageMappings @param array<int,array<string,mixed>> $enumMappings @return array<int,array<string,string>> */
    private function detectConflicts(array $source, array $target, array $fieldMappings, array $stageMappings, array $enumMappings): array
    {
        $conflicts = [];

        foreach ($target['entities'] as $entityCode => $entity) {
            foreach ($entity['fields'] as $fieldCode => $field) {
                if (($field['required'] ?? false) !== true) {
                    continue;
                }

                $hasMapping = false;
                foreach ($fieldMappings as $mapping) {
                    if ($mapping['entity'] === $entityCode && $mapping['target_field'] === $fieldCode) {
                        $hasMapping = true;
                        break;
                    }
                }

                if (!$hasMapping) {
                    $conflicts[] = [
                        'type' => 'required_field_without_source',
                        'message' => sprintf('%s.%s is required in target but has no source mapping', $entityCode, $fieldCode),
                    ];
                }
            }
        }

        foreach ($fieldMappings as $mapping) {
            if (($mapping['transformation_rule'] ?? '') === 'text_to_string_truncate') {
                $conflicts[] = ['type' => 'precision_loss', 'message' => sprintf('%s.%s may lose content by truncation', $mapping['entity'], $mapping['source_field'])];
            }
            if (($mapping['transformation_rule'] ?? '') === 'incompatible') {
                $conflicts[] = ['type' => 'incompatible_types', 'message' => sprintf('%s.%s has incompatible field type', $mapping['entity'], $mapping['source_field'])];
            }
        }

        foreach ($stageMappings as $stageMapping) {
            if (($stageMapping['status'] ?? '') === 'needs_creation') {
                $conflicts[] = ['type' => 'unmapped_stage', 'message' => sprintf('%s.%s missing in target', $stageMapping['entity'], (string) ($stageMapping['source_stage']['name'] ?? 'unknown'))];
            }
        }

        foreach ($enumMappings as $enumMapping) {
            if (($enumMapping['status'] ?? '') === 'needs_creation') {
                $conflicts[] = ['type' => 'enum_without_match', 'message' => sprintf('%s.%s value %s unresolved', $enumMapping['entity'], $enumMapping['field'], (string) $enumMapping['source_value'])];
            }
        }

        return $conflicts;
    }

    private function typeTransformation(string $sourceType, string $targetType): string
    {
        if ($sourceType === $targetType) {
            return 'none';
        }

        return match (sprintf('%s:%s', $sourceType, $targetType)) {
            'text:string' => 'text_to_string_truncate',
            'string:text' => 'string_to_text',
            'enumeration:string' => 'enum_to_string',
            'string:enumeration' => 'string_to_enum_lookup',
            'datetime:datetime' => 'datetime_timezone_normalization',
            'integer:double', 'double:integer' => 'number_format_normalization',
            'boolean:string', 'string:boolean' => 'boolean_normalization',
            default => 'incompatible',
        };
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?? $value;
    }

    private function semanticKey(string $value): string
    {
        $normalized = $this->normalize($value);
        $dictionary = [
            'accountowner' => 'responsiblemanager',
            'responsiblemanager' => 'responsiblemanager',
            'projectbudget' => 'budget',
            'бюджетпроекта' => 'budget',
            'budget' => 'budget',
            'phone' => 'phone',
            'телефон' => 'phone',
        ];

        return $dictionary[$normalized] ?? $normalized;
    }

    private function confidence(int $score): string
    {
        if ($score >= 80) {
            return 'high';
        }
        if ($score >= 50) {
            return 'medium';
        }

        return 'low';
    }

    /** @param array<int,array<string,mixed>> $mappings */
    private function coverage(array $mappings): int
    {
        $auto = count(array_filter($mappings, static fn ($m): bool => ($m['status'] ?? '') === 'auto'));

        return $this->percent($auto, max(1, count($mappings)));
    }

    private function percent(int $value, int $total): int
    {
        return (int) round(($value / max(1, $total)) * 100);
    }
}
