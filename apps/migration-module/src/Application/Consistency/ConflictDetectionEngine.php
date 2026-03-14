<?php

declare(strict_types=1);

namespace MigrationModule\Application\Consistency;

final class ConflictDetectionEngine
{
    /** @param array<string,mixed> $ctx */
    public function detect(array $ctx): ?array
    {
        if (($ctx['mapping_exists'] ?? false) && !($ctx['target_exists'] ?? true)) {
            return $this->conflict('mapping_exists_target_missing', 'high', ['repair_mapping']);
        }

        if (!($ctx['mapping_exists'] ?? true) && ($ctx['target_exists'] ?? false)) {
            return $this->conflict('target_exists_mapping_missing', 'medium', ['manual_review']);
        }

        if (($ctx['source_changed'] ?? false) && ($ctx['target_changed'] ?? false)) {
            return $this->conflict('source_and_target_changed', 'high', ['latest_timestamp_wins', 'manual_review']);
        }

        if (($ctx['target_changed_manually'] ?? false) && ($ctx['source_changed'] ?? false)) {
            return $this->conflict('manual_target_edit_detected', 'critical', ['target_wins', 'manual_review']);
        }

        if (($ctx['file_checksum_mismatch'] ?? false) === true) {
            return $this->conflict('file_version_mismatch', 'medium', ['reupload_file']);
        }

        return null;
    }

    /** @param array<int,string> $options */
    private function conflict(string $type, string $severity, array $options): array
    {
        return [
            'conflict_id' => 'conf-' . substr(hash('sha1', $type . microtime(true)), 0, 10),
            'type' => $type,
            'severity' => $severity,
            'safe_auto_resolution_options' => $options,
            'manual_resolution_required' => in_array('manual_review', $options, true) || $severity === 'critical',
        ];
    }
}
