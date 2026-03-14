<?php

declare(strict_types=1);

namespace MigrationModule\ControlCenter\API;

use MigrationModule\ControlCenter\Controller\ConflictController;
use MigrationModule\ControlCenter\Controller\DashboardController;
use MigrationModule\ControlCenter\Controller\DiffController;
use MigrationModule\ControlCenter\Controller\IntegrityController;

final class ControlCenterApi
{
    public function __construct(
        private readonly DashboardController $dashboardController,
        private readonly DiffController $diffController,
        private readonly ConflictController $conflictController,
        private readonly IntegrityController $integrityController,
    ) {
    }

    /** @return array<string,mixed>|array<int,array<string,mixed>> */
    public function handle(string $path, array $payload = []): array
    {
        if (preg_match('#^/migration/diff/([^/]+)/([^/]+)$#', $path, $matches) === 1) {
            return $this->diffController->json($matches[1], $matches[2], $payload['source'] ?? [], $payload['target'] ?? []);
        }

        return match ($path) {
            '/migration/dashboard' => $this->dashboardController->json((string) ($payload['job_id'] ?? '')),
            '/migration/conflicts' => $this->conflictController->list((string) ($payload['job_id'] ?? ''), (int) ($payload['limit'] ?? 100), (int) ($payload['offset'] ?? 0)),
            '/migration/conflicts/resolve' => $this->conflictController->resolve((string) ($payload['job_id'] ?? ''), (array) ($payload['conflict'] ?? []), (string) ($payload['action'] ?? 'manual_edit')),
            '/migration/integrity' => $this->integrityController->list((string) ($payload['job_id'] ?? ''), (int) ($payload['limit'] ?? 100), (int) ($payload['offset'] ?? 0)),
            '/migration/integrity/repair' => $this->integrityController->repair((string) ($payload['job_id'] ?? ''), (array) ($payload['issue'] ?? []), (bool) ($payload['confirmed'] ?? false)),
            '/migration/velocity-audit' => $this->dashboardController->velocityAudit(),
            default => ['error' => 'unknown_endpoint', 'path' => $path],
        };
    }
}
