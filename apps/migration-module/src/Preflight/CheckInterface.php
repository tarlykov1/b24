<?php

declare(strict_types=1);

namespace MigrationModule\Preflight;

interface CheckInterface
{
    public function run(): CheckResult;
}
