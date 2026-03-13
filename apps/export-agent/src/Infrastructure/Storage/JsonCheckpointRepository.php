<?php

declare(strict_types=1);

namespace ExportAgent\Infrastructure\Storage;

use ExportAgent\Domain\CheckpointRepositoryInterface;

final class JsonCheckpointRepository implements CheckpointRepositoryInterface
{
    public function __construct(private readonly string $path)
    {
    }

    public function get(string $scope): ?string
    {
        return null;
    }

    public function save(string $scope, string $cursor): void
    {
        // TODO: persist scope cursor map.
    }
}
