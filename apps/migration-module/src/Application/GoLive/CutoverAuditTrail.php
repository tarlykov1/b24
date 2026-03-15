<?php

declare(strict_types=1);

namespace MigrationModule\Application\GoLive;

use DateTimeImmutable;

final class CutoverAuditTrail
{
    /** @var array<int,array<string,mixed>> */
    private array $events = [];

    /** @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function append(string $actorId, string $action, array $payload): array
    {
        $prevHash = $this->events === [] ? 'genesis' : (string) $this->events[array_key_last($this->events)]['hash'];
        $event = [
            'actorId' => $actorId,
            'action' => $action,
            'payload' => $payload,
            'timestamp' => (new DateTimeImmutable())->format(DATE_ATOM),
            'prevHash' => $prevHash,
        ];
        $event['hash'] = hash('sha256', json_encode($event, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $this->events[] = $event;

        return $event;
    }

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return $this->events;
    }
}
