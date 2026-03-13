<?php

declare(strict_types=1);

namespace MigrationModule\Application\Assistant;

final class KnowledgeBaseRepository
{
    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    public function __construct(private readonly string $rulePackPath)
    {
    }

    /** @return array<string, mixed> */
    public function load(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        if (!is_file($this->rulePackPath)) {
            return $this->cache = [];
        }

        $decoded = json_decode((string) file_get_contents($this->rulePackPath), true);

        return $this->cache = is_array($decoded) ? $decoded : [];
    }
}
