<?php

declare(strict_types=1);

namespace MigrationModule\Application\Sync;

use MigrationModule\Application\Logging\MigrationLogger;

final class ApiRequestExecutor
{
    public function __construct(private readonly MigrationLogger $logger)
    {
    }

    /** @param callable():array<string,mixed> $request */
    public function executeWithRetry(callable $request, string $operation, string $entityType, ?string $entityId): ?array
    {
        $attemptDelays = [0, 3, 5];
        foreach ($attemptDelays as $attempt => $delay) {
            if ($delay > 0) {
                sleep($delay);
            }

            try {
                return $request();
            } catch (\Throwable $exception) {
                $this->logger->warning($operation, $entityType, $entityId, sprintf('Attempt %d failed: %s', $attempt + 1, $exception->getMessage()));
            }
        }

        $this->logger->error($operation, $entityType, $entityId, 'Request failed after 3 attempts');

        return null;
    }
}
