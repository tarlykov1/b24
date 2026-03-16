<?php

declare(strict_types=1);

namespace MigrationModule\Preflight;

use PDO;

final class CheckContext
{
    /** @param array<string,mixed> $config */
    public function __construct(
        public readonly array $config,
        public readonly object $sourceAdapter,
        public readonly object $targetAdapter,
        public readonly ?PDO $storagePdo = null,
    ) {
    }

    public function configValue(string $section, string $key, mixed $default = null): mixed
    {
        $chunk = $this->config[$section] ?? null;
        if (!is_array($chunk)) {
            return $default;
        }

        return $chunk[$key] ?? $default;
    }
}
