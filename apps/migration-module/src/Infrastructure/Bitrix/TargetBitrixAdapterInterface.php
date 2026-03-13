<?php

declare(strict_types=1);

namespace MigrationModule\Infrastructure\Bitrix;

interface TargetBitrixAdapterInterface
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(string $entityType, array $payload): array;

    /**
     * @param array<string, mixed> $payload
     */
    public function update(string $entityType, int|string $targetId, array $payload): void;
}
