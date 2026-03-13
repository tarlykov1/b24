<?php

declare(strict_types=1);

namespace MigrationModule\Application\Execution;

use RuntimeException;
use Throwable;

final class RetryService
{
    /** @return mixed */
    public function run(callable $operation, int $maxAttempts = 3, int $baseDelayMs = 10)
    {
        $attempt = 0;
        beginning:
        $attempt++;

        try {
            return $operation($attempt);
        } catch (Throwable $e) {
            if ($attempt >= $maxAttempts) {
                throw new RuntimeException('Retry attempts exceeded', 0, $e);
            }

            usleep($baseDelayMs * $attempt * 1000);
            goto beginning;
        }
    }
}
