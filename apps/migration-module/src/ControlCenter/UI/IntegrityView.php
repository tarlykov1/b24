<?php

declare(strict_types=1);

namespace MigrationModule\ControlCenter\UI;

final class IntegrityView
{
    /** @param array<int,array<string,mixed>> $issues */
    public function render(array $issues): string
    {
        $lines = ['ISSUE | ENTITY | ID | REPAIR | ACTIONS'];

        foreach ($issues as $issue) {
            $lines[] = sprintf(
                '%s | %s | %s | %s | [repair single] [repair batch] [auto repair] [schedule repair]',
                (string) ($issue['issue'] ?? 'unknown'),
                (string) ($issue['entity'] ?? 'unknown'),
                (string) ($issue['id'] ?? ''),
                (string) ($issue['repair'] ?? 'manual'),
            );
        }

        return implode(PHP_EOL, $lines);
    }
}
