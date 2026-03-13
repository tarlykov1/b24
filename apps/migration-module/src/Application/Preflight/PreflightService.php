<?php

declare(strict_types=1);

namespace MigrationModule\Application\Preflight;

use MigrationModule\Domain\Config\InactiveUserPolicy;
use MigrationModule\Domain\Config\JobSettings;

final class PreflightService
{
    /** @param array<int, PreflightCheckInterface> $checks */
    public function __construct(private readonly array $checks)
    {
    }

    /** @return array{status:string, checks:array<int, array{name:string,status:string,message:string}>} */
    public function run(): array
    {
        $results = [];
        $hasError = false;
        foreach ($this->checks as $check) {
            $result = $check->run();
            $results[] = $result;
            if ($result['status'] !== 'PASS') {
                $hasError = true;
            }
        }

        return [
            'status' => $hasError ? 'BLOCKED' : 'PASS',
            'checks' => $results,
        ];
    }
}
