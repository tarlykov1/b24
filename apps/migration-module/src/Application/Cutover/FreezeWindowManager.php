<?php

declare(strict_types=1);

namespace MigrationModule\Application\Cutover;

final class FreezeWindowManager
{
    /** @param list<array<string,mixed>> $mutations @param list<string> $protectedDomains @return array<string,mixed> */
    public function evaluateMutations(string $mode, array $mutations, array $protectedDomains): array
    {
        $blocking = 0;
        $counts = [];
        foreach ($mutations as $m) {
            $type = (string) ($m['entity_type'] ?? 'unknown');
            $counts[$type] = ($counts[$type] ?? 0) + 1;
            $isProtected = in_array($type, $protectedDomains, true);
            if ($mode === 'strict_freeze' && $isProtected) {
                $blocking++;
            }
        }

        return [
            'mode' => $mode,
            'mutations_detected_total' => count($mutations),
            'blocking_mutations' => $blocking,
            'counts_by_entity_type' => $counts,
            'freeze_policy_result' => $mode === 'detect_only' ? 'observed_only' : ($blocking > 0 ? 'blocked' : 'allowed'),
            'enforcement_honesty_note' => 'Runtime does mutation detection and policy enforcement. It does not enforce global Bitrix write lock by itself.',
        ];
    }
}
