<?php

declare(strict_types=1);

namespace MigrationModule\ControlCenter\Controller;

use MigrationModule\ControlCenter\Service\MigrationMonitor;
use MigrationModule\ControlCenter\UI\DashboardView;

final class DashboardController
{
    public function __construct(
        private readonly MigrationMonitor $monitor,
        private readonly DashboardView $view,
    ) {
    }

    /** @return array<string,mixed> */
    public function json(string $jobId): array
    {
        return $this->monitor->dashboard($jobId);
    }


    /** @return array<string,mixed> */
    public function velocityAudit(): array
    {
        return $this->monitor->velocityAudit();
    }

    public function html(string $jobId): string
    {
        return $this->view->render($this->json($jobId));
    }
}
