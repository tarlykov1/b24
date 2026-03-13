<?php

declare(strict_types=1);

namespace MigrationModule\Application\Preflight;

interface PreflightCheckInterface
{
    /** @return array{name:string,status:string,message:string} */
    public function run(): array;
}
