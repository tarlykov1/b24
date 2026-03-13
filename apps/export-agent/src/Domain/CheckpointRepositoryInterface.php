<?php

declare(strict_types=1);

namespace ExportAgent\Domain;

interface CheckpointRepositoryInterface
{
    public function get(string $scope): ?string;

    public function save(string $scope, string $cursor): void;
}
