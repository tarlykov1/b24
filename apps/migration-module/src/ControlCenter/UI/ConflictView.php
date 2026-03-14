<?php

declare(strict_types=1);

namespace MigrationModule\ControlCenter\UI;

final class ConflictView
{
    /** @param array<int,array<string,mixed>> $conflicts */
    public function render(array $conflicts): string
    {
        $lines = ['TYPE | ENTITY | ENTITY_ID | SUGGESTED | ACTIONS'];
        foreach ($conflicts as $conflict) {
            $lines[] = sprintf(
                '%s | %s | %s | %s | [remap] [skip] [merge] [create new] [assign system user] [manual edit]',
                (string) ($conflict['type'] ?? 'unknown'),
                (string) ($conflict['entity'] ?? 'unknown'),
                (string) ($conflict['entity_id'] ?? ''),
                (string) ($conflict['suggested_resolution'] ?? 'manual_edit'),
            );
        }

        return implode(PHP_EOL, $lines);
    }
}
