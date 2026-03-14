<?php

declare(strict_types=1);

namespace MigrationModule\ControlCenter\UI;

final class DiffView
{
    /** @param array<string,mixed> $diffPayload */
    public function render(array $diffPayload): string
    {
        $lines = [
            'FIELD           SOURCE              TARGET              STATUS',
            '---------------------------------------------------------------',
        ];

        foreach (($diffPayload['diff'] ?? []) as $row) {
            $status = (string) ($row['status'] ?? 'unknown');
            $color = match ($status) {
                'identical' => 'green',
                'changed' => 'yellow',
                default => 'red',
            };

            $lines[] = sprintf(
                '%-15s %-19s %-19s %s(%s)',
                (string) ($row['field'] ?? ''),
                (string) (($row['source'] ?? '∅')),
                (string) (($row['target'] ?? '∅')),
                $status,
                $color,
            );
        }

        $lines[] = '[accept source] [accept target] [manual edit] [ignore]';

        return implode(PHP_EOL, $lines);
    }
}
