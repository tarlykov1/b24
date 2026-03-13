<?php

declare(strict_types=1);

namespace MigrationModule\Application\Orchestrator\Contracts;

interface PlannerInterface
{
    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function buildPlan(array $context): array;
}
