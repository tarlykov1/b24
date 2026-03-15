<?php

declare(strict_types=1);

namespace MigrationModule\Application\Hypercare;

final class PostMigrationIntegrityScanner
{
    /** @param array<string,list<array<string,mixed>>> $source
     * @param array<string,list<array<string,mixed>>> $target
     * @return array<string,mixed>
     */
    public function scan(array $source, array $target): array
    {
        $entities = ['users', 'companies', 'contacts', 'deals', 'tasks', 'comments', 'files', 'smart_processes', 'activities', 'timeline', 'permissions'];
        $checks = [];
        $issues = [];

        foreach ($entities as $entity) {
            $src = $source[$entity] ?? [];
            $tgt = $target[$entity] ?? [];
            $checks[$entity] = ['source_count' => count($src), 'target_count' => count($tgt), 'status' => count($src) === count($tgt) ? 'ok' : 'mismatch'];
            if (count($src) !== count($tgt)) {
                $issues[] = ['type' => 'count_mismatch', 'entity' => $entity, 'severity' => 'high'];
            }
        }

        $issues = array_merge($issues, $this->relationIssues($target), $this->fileIssues($source['files'] ?? [], $target['files'] ?? []));

        return [
            'summary' => ['total_checks' => count($checks), 'issues' => count($issues)],
            'checks' => $checks,
            'issues' => $issues,
            'integrity_score' => round(max(0.0, 1.0 - (count($issues) / max(1, count($checks) * 3))), 4),
        ];
    }

    /** @param array<string,list<array<string,mixed>>> $target
     * @return list<array<string,mixed>>
     */
    private function relationIssues(array $target): array
    {
        $issues = [];
        $contactIds = array_fill_keys(array_map(static fn (array $c): string => (string) ($c['id'] ?? ''), $target['contacts'] ?? []), true);
        foreach (($target['deals'] ?? []) as $deal) {
            $contactId = (string) ($deal['contact_id'] ?? '');
            if ($contactId !== '' && !isset($contactIds[$contactId])) {
                $issues[] = ['type' => 'broken_reference', 'entity' => 'deals', 'entity_id' => (string) ($deal['id'] ?? ''), 'field' => 'contact_id', 'severity' => 'critical'];
            }
        }

        return $issues;
    }

    /** @param list<array<string,mixed>> $sourceFiles
     * @param list<array<string,mixed>> $targetFiles
     * @return list<array<string,mixed>>
     */
    private function fileIssues(array $sourceFiles, array $targetFiles): array
    {
        $issues = [];
        $targetById = [];
        foreach ($targetFiles as $file) {
            $targetById[(string) ($file['id'] ?? '')] = $file;
        }
        foreach ($sourceFiles as $file) {
            $id = (string) ($file['id'] ?? '');
            if ($id === '' || !isset($targetById[$id])) {
                $issues[] = ['type' => 'lost_file', 'entity' => 'files', 'entity_id' => $id, 'severity' => 'high'];
                continue;
            }
            if (($file['checksum'] ?? null) !== ($targetById[$id]['checksum'] ?? null)) {
                $issues[] = ['type' => 'file_checksum_mismatch', 'entity' => 'files', 'entity_id' => $id, 'severity' => 'high'];
            }
        }

        return $issues;
    }
}
