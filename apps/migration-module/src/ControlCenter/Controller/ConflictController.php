<?php

declare(strict_types=1);

namespace MigrationModule\ControlCenter\Controller;

use MigrationModule\ControlCenter\Service\ConflictResolver;
use MigrationModule\ControlCenter\UI\ConflictView;

final class ConflictController
{
    public function __construct(
        private readonly ConflictResolver $resolver,
        private readonly ConflictView $view,
    ) {
    }

    /** @return array<int,array<string,mixed>> */
    public function list(string $jobId, int $limit = 100, int $offset = 0): array
    {
        return $this->resolver->list($jobId, $limit, $offset);
    }

    /** @param array<string,mixed> $conflict
     * @return array<string,mixed>
     */
    public function resolve(string $jobId, array $conflict, string $action): array
    {
        return $this->resolver->resolve($jobId, $conflict, $action);
    }

    public function autoResolve(string $jobId, string $policy): array
    {
        return $this->resolver->autoResolveWithPolicy($jobId, $policy);
    }

    public function html(string $jobId, int $limit = 100, int $offset = 0): string
    {
        return $this->view->render($this->list($jobId, $limit, $offset));
    }
}
