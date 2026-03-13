<?php

declare(strict_types=1);

namespace ExportAgent\Application;

use ExportAgent\Domain\CheckpointRepositoryInterface;
use ExportAgent\Domain\ExportBatch;
use ExportAgent\Infrastructure\Bitrix\SourceBitrixAdapterInterface;

final class ExportAgent
{
    public function __construct(
        private readonly SourceBitrixAdapterInterface $bitrix,
        private readonly CheckpointRepositoryInterface $checkpoints,
    ) {
    }

    public function exportBatch(string $entityType, int $batchSize): ExportBatch
    {
        // TODO: read checkpoint, pull source-safe batch, persist checkpoint.
        return new ExportBatch($entityType, [], null);
    }
}
