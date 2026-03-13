<?php

declare(strict_types=1);

namespace MigrationModule\Application\Sync;

use DateTimeImmutable;

final class CutoffService
{
    public function shouldSync(string $updatedAt, string $cutoff): bool
    {
        return new DateTimeImmutable($updatedAt) >= new DateTimeImmutable($cutoff);
    }
}
