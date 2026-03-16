<?php

declare(strict_types=1);

namespace MigrationModule\Preflight;

final class CheckResult
{
    /** @param array<string,mixed> $data */
    public function __construct(
        public readonly string $name,
        public readonly string $status,
        public readonly string $details = '',
        public readonly array $data = [],
        public readonly array $guidance = [],
    ) {
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        $payload = [
            'name' => $this->name,
            'status' => $this->status,
        ];

        if ($this->details !== '') {
            $payload['details'] = $this->details;
        }
        if ($this->data !== []) {
            $payload['data'] = $this->data;
        }
        if ($this->guidance !== []) {
            $payload['guidance'] = $this->guidance;
        }

        return $payload;
    }
}
