<?php

declare(strict_types=1);

namespace MigrationModule\Application\Sync;

final class ConflictResolutionService
{
    /** @param array<string, mixed> $source @param array<string, mixed> $target @return array<string, mixed> */
    public function resolve(array $source, array $target, string $strategy = 'source_wins'): array
    {
        return match ($strategy) {
            'target_wins' => $target,
            'merge_non_empty' => array_merge($target, array_filter($source, static fn ($value): bool => $value !== null && $value !== '')),
            default => array_merge($target, $source),
        };
    }
}
