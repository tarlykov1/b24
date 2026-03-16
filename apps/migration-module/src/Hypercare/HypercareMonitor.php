<?php

declare(strict_types=1);

namespace MigrationModule\Hypercare;

final class HypercareMonitor
{
    private const ENTITIES = ['users', 'departments', 'contacts', 'companies', 'deals', 'tasks', 'comments', 'activities', 'smart_processes', 'files', 'disk_objects'];

    public function scan(array $source, array $target, string $detectedAt = 'now'): array
    {
        $issues = [];
        foreach (self::ENTITIES as $entityType) {
            $src = $source[$entityType] ?? [];
            $tgt = $target[$entityType] ?? [];
            if (count($src) !== count($tgt)) {
                $issues[] = $this->issue($entityType, 'count', 'warning', 'Entity count mismatch detected.', ['count' => count($src)], ['count' => count($tgt)], $detectedAt);
            }

            $targetById = [];
            foreach ($tgt as $row) {
                $targetById[(string) ($row['id'] ?? '')] = $row;
            }
            foreach ($src as $row) {
                $id = (string) ($row['id'] ?? '');
                if ($id !== '' && !isset($targetById[$id])) {
                    $issues[] = $this->issue($entityType, $id, 'critical', 'Missing entity in target.', $row, null, $detectedAt);
                }
            }
        }

        foreach (($target['deals'] ?? []) as $deal) {
            $contactId = (string) ($deal['contact_id'] ?? '');
            $dealId = (string) ($deal['id'] ?? '');
            if ($contactId !== '' && !$this->existsById($target['contacts'] ?? [], $contactId)) {
                $issues[] = $this->issue('deals', $dealId, 'critical', 'Broken reference: deal contact not found.', $deal, ['missing_contact_id' => $contactId], $detectedAt);
            }
        }

        foreach (($source['files'] ?? []) as $sourceFile) {
            $id = (string) ($sourceFile['id'] ?? '');
            $targetFile = $this->findById($target['files'] ?? [], $id);
            if ($targetFile === null) {
                $issues[] = $this->issue('files', $id, 'warning', 'File attachment missing in target.', $sourceFile, null, $detectedAt);
                continue;
            }
            if (($sourceFile['disk_object_id'] ?? null) !== ($targetFile['disk_object_id'] ?? null)) {
                $issues[] = $this->issue('disk_objects', (string) ($targetFile['disk_object_id'] ?? ''), 'warning', 'Disk object inconsistency found for file.', $sourceFile, $targetFile, $detectedAt);
            }
        }

        return [
            'hypercare_issues' => $issues,
            'summary' => [
                'issues_total' => count($issues),
                'critical' => count(array_filter($issues, static fn (array $i): bool => $i['severity'] === 'critical')),
                'warning' => count(array_filter($issues, static fn (array $i): bool => $i['severity'] === 'warning')),
            ],
        ];
    }

    private function existsById(array $rows, string $id): bool
    {
        return $this->findById($rows, $id) !== null;
    }

    private function findById(array $rows, string $id): ?array
    {
        foreach ($rows as $row) {
            if ((string) ($row['id'] ?? '') === $id) {
                return $row;
            }
        }

        return null;
    }

    private function issue(string $entityType, string $entityId, string $severity, string $description, mixed $sourceReference, mixed $targetReference, string $detectedAt): array
    {
        return [
            'issue_id' => 'hc_issue_' . substr(hash('sha256', $entityType . ':' . $entityId . ':' . $description), 0, 12),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'severity' => $severity,
            'description' => $description,
            'source_reference' => $sourceReference,
            'target_reference' => $targetReference,
            'detected_at' => (new \DateTimeImmutable($detectedAt))->format(DATE_ATOM),
            'repair_status' => 'pending',
        ];
    }
}
