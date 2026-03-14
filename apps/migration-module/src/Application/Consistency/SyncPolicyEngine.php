<?php

declare(strict_types=1);

namespace MigrationModule\Application\Consistency;

final class SyncPolicyEngine
{
    /** @param array<string,mixed> $context */
    public function decide(string $entityType, string $policy, array $context): array
    {
        $sourceChanged = (bool) ($context['source_changed'] ?? false);
        $targetChanged = (bool) ($context['target_changed'] ?? false);
        $targetManual = (bool) ($context['target_changed_manually'] ?? false);
        $targetExists = (bool) ($context['target_exists'] ?? false);

        if (!$targetExists) {
            return ['action' => 'create', 'reason' => 'target_missing'];
        }

        if ($targetManual && $sourceChanged) {
            return ['action' => 'conflict', 'reason' => 'source_and_target_changed'];
        }

        return match ($policy) {
            'create_only' => ['action' => 'skip', 'reason' => 'create_only'],
            'create_or_update' => ['action' => $sourceChanged ? 'update' : 'skip', 'reason' => 'create_or_update'],
            'update_if_source_newer' => ['action' => (($context['source_updated_at'] ?? '') > ($context['target_updated_at'] ?? '')) ? 'update' : 'skip', 'reason' => 'timestamp_gate'],
            'update_if_target_untouched' => ['action' => $targetChanged ? 'skip' : 'update', 'reason' => 'target_touch_gate'],
            'conflict_on_both_changed' => ['action' => ($sourceChanged && $targetChanged) ? 'conflict' : ($sourceChanged ? 'update' : 'skip'), 'reason' => 'both_changed_policy'],
            'skip_if_target_exists' => ['action' => 'skip', 'reason' => 'target_exists'],
            'manual_review_required' => ['action' => 'manual_review', 'reason' => 'policy_forced_review'],
            default => ['action' => 'skip', 'reason' => 'unknown_policy'],
        };
    }
}
