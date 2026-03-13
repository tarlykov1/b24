<?php

declare(strict_types=1);

namespace MigrationModule\Application\Preflight;

use Closure;

final class CallbackPreflightCheck implements PreflightCheckInterface
{
    public function __construct(
        private readonly string $name,
        private readonly string $errorMessage,
        private readonly Closure $callback,
    ) {
    }

    public function run(): array
    {
        $ok = ($this->callback)();

        return [
            'name' => $this->name,
            'status' => $ok ? 'PASS' : 'FAIL',
            'message' => $ok ? 'OK' : $this->errorMessage,
        ];
    }
}
