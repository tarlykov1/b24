<?php

declare(strict_types=1);

namespace MigrationModule\Application\Checkpoint;

final class CheckpointService
{
    private int $lastSavedAt = 0;
    private int $processedSinceSave = 0;

    public function __construct(
        private readonly string $stateFile = 'migration_state.json',
        private readonly int $everyNObjects = 100,
        private readonly int $everyMSeconds = 30,
    ) {
    }

    /** @param array<string,mixed> $queue */
    public function advance(string $stage, ?string $lastEntity, array $queue, int $processedDelta = 1): void
    {
        $this->processedSinceSave += $processedDelta;
        $now = time();

        if ($this->processedSinceSave < $this->everyNObjects && ($now - $this->lastSavedAt) < $this->everyMSeconds) {
            return;
        }

        $this->save($stage, $lastEntity, $queue);
    }

    /** @param array<string,mixed> $queue */
    public function save(string $stage, ?string $lastEntity, array $queue): void
    {
        $state = [
            'stage' => $stage,
            'last_entity' => $lastEntity,
            'queue_state' => $queue,
            'saved_at' => date(DATE_ATOM),
        ];

        file_put_contents($this->stateFile, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->lastSavedAt = time();
        $this->processedSinceSave = 0;
    }

    /** @return array<string,mixed>|null */
    public function load(): ?array
    {
        if (!is_file($this->stateFile)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($this->stateFile), true);

        return is_array($decoded) ? $decoded : null;
    }
}
