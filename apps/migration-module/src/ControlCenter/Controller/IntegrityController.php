<?php

declare(strict_types=1);

namespace MigrationModule\ControlCenter\Controller;

use MigrationModule\ControlCenter\Service\IntegrityRepairService;
use MigrationModule\ControlCenter\UI\IntegrityView;

final class IntegrityController
{
    public function __construct(
        private readonly IntegrityRepairService $service,
        private readonly IntegrityView $view,
    ) {
    }

    /** @return array<int,array<string,mixed>> */
    public function list(string $jobId, int $limit = 100, int $offset = 0): array
    {
        return $this->service->issues($jobId, $limit, $offset);
    }

    /** @param array<string,mixed> $issue
     * @return array<string,mixed>
     */
    public function repair(string $jobId, array $issue, bool $confirmed = false): array
    {
        return $this->service->repairIssue($jobId, $issue, $confirmed);
    }

    public function autoRepair(string $jobId, bool $confirmed = false): array
    {
        return $this->service->autoRepair($jobId, $confirmed);
    }

    public function scheduleRepair(string $jobId, string $when): array
    {
        return $this->service->scheduleRepair($jobId, $when);
    }

    public function html(string $jobId, int $limit = 100, int $offset = 0): string
    {
        return $this->view->render($this->list($jobId, $limit, $offset));
    }
}
