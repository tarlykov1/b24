<?php

declare(strict_types=1);

namespace MigrationModule\Application\Mapping;

final class ReferenceResolver
{
    /** @param array<string, string> $mapping */
    public function resolve(array $entity, array $mapping, string ...$fields): array
    {
        $resolved = $entity;
        foreach ($fields as $field) {
            $key = sprintf('%s:%s', str_replace('_id', '', $field), (string) ($entity[$field] ?? ''));
            if (isset($mapping[$key])) {
                $resolved[$field] = $mapping[$key];
            }
        }

        return $resolved;
    }
}
