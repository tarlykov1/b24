<?php

declare(strict_types=1);

namespace MigrationModule\Application\Assistant;

final class PreflightAssessmentService
{
    public function __construct(private readonly MigrationAssistantService $assistant)
    {
    }

    /** @param array<string,mixed> $snapshot @param array<int,array<string,mixed>> $history @return array<string,mixed> */
    public function run(array $snapshot, array $history = []): array
    {
        return $this->assistant->assess($snapshot, $history, 'advisory', false, false);
    }
}
