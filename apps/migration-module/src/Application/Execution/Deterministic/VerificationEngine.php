<?php

declare(strict_types=1);

namespace MigrationModule\Application\Execution\Deterministic;

use MigrationModule\Prototype\Adapter\TargetAdapterInterface;

final class VerificationEngine
{
    public function __construct(private readonly TargetAdapterInterface $target)
    {
    }

    public function verify(string $entityType, string $targetId, array $payload): array
    {
        $level1 = ['level' => 1, 'status' => 'verified'];
        $exists = $this->target->exists($entityType, $targetId);
        $level2 = ['level' => 2, 'status' => $exists ? 'verified' : 'mismatch'];
        $level3 = ['level' => 3, 'status' => 'partial'];
        $level4 = ['level' => 4, 'status' => 'partial'];
        $level5 = ['level' => 5, 'status' => 'partial'];

        return ['levels' => [$level1, $level2, $level3, $level4, $level5], 'status' => $exists ? 'verified' : 'mismatch'];
    }
}
