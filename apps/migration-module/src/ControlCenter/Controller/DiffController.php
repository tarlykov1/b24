<?php

declare(strict_types=1);

namespace MigrationModule\ControlCenter\Controller;

use MigrationModule\ControlCenter\Service\DiffEngine;
use MigrationModule\ControlCenter\UI\DiffView;

final class DiffController
{
    public function __construct(
        private readonly DiffEngine $engine,
        private readonly DiffView $view,
    ) {
    }

    /** @param array<string,mixed> $source @param array<string,mixed> $target
     * @return array<string,mixed>
     */
    public function json(string $entity, string|int $id, array $source, array $target): array
    {
        return $this->engine->compare($entity, $id, $source, $target);
    }

    /** @param array<string,mixed> $source @param array<string,mixed> $target */
    public function html(string $entity, string|int $id, array $source, array $target): string
    {
        return $this->view->render($this->json($entity, $id, $source, $target));
    }
}
