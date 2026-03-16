<?php

declare(strict_types=1);

namespace MigrationModule\Application\DeltaUpload;

use MigrationModule\Prototype\Storage\SqliteStorage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class BaselineDeltaServices
{
    public function __construct(private readonly SqliteStorage $storage)
    {
    }

    /** @return array<string,mixed> */
    public function baselineIndex(string $jobId, string $sourceRoot, string $targetRoot, int $chunkSize = 500, string $verificationMode = 'fast'): array
    {
        $baselineId = $this->storage->createBaselineSnapshot($jobId, $sourceRoot, $targetRoot, $verificationMode);
        $count = 0;
        $bytes = 0;
        $chunk = [];
        foreach ($this->walk($sourceRoot) as $file) {
            $relative = PathSafety::normalizeRelative(substr($file, strlen(realpath($sourceRoot)) + 1));
            $targetPath = rtrim($targetRoot, '/') . '/' . $relative;
            $size = filesize($file) ?: 0;
            $mtime = filemtime($file) ?: 0;
            $presentTarget = is_file($targetPath);
            $fingerprint = $relative . '|' . $size . '|' . $mtime;
            $checksum = null;
            if ($verificationMode === 'strict' || ($verificationMode === 'balanced' && $size <= 1024 * 1024)) {
                $checksum = hash_file('sha256', $file) ?: null;
            }

            $chunk[] = [
                'relative_path' => $relative,
                'size_bytes' => $size,
                'mtime_epoch' => $mtime,
                'checksum_sha256' => $checksum,
                'fingerprint' => hash('sha1', $fingerprint),
                'scan_ts' => date(DATE_ATOM),
                'source_present' => 1,
                'target_present' => $presentTarget ? 1 : 0,
                'reuse_eligible' => $presentTarget ? 1 : 0,
                'conflict_flag' => 0,
            ];
            $count++;
            $bytes += $size;

            if (count($chunk) >= $chunkSize) {
                $this->storage->insertBaselineFiles($baselineId, $chunk);
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            $this->storage->insertBaselineFiles($baselineId, $chunk);
        }

        $this->storage->completeBaselineSnapshot($baselineId, $count, $bytes);

        return ['baseline_id' => $baselineId, 'indexed_files' => $count, 'indexed_bytes' => $bytes, 'verification_mode' => $verificationMode];
    }

    /** @return array<string,mixed> */
    public function deltaScan(string $jobId, string $baselineId, string $sourceRoot, string $targetRoot, array $referencedPaths = []): array
    {
        $scanId = $this->storage->createDeltaScan($jobId, $baselineId, ['source_root' => $sourceRoot, 'target_root' => $targetRoot]);
        $baseline = $this->storage->baselineFilesMap($baselineId);
        $sourceMap = $this->treeMap($sourceRoot);
        $targetMap = $this->treeMap($targetRoot);

        $allPaths = array_values(array_unique(array_merge(array_keys($baseline), array_keys($sourceMap), array_keys($targetMap))));
        sort($allPaths);
        $counts = [];
        $items = [];
        foreach ($allPaths as $path) {
            $b = $baseline[$path] ?? null;
            $s = $sourceMap[$path] ?? null;
            $t = $targetMap[$path] ?? null;
            $status = 'UNVERIFIED';
            $reason = 'ambiguous';
            if ($s !== null && $b === null && $t === null) {
                $status = 'NEW';
                $reason = 'absent_in_baseline_and_target';
            } elseif ($s !== null && $t === null) {
                $status = 'MISSING_ON_TARGET';
                $reason = 'source_exists_target_missing';
            } elseif ($s === null && $t !== null) {
                $status = 'TARGET_ONLY';
                $reason = 'missing_on_source';
            } elseif ($s !== null && $b !== null && ((int) $b['size_bytes'] !== $s['size'] || (int) $b['mtime_epoch'] !== $s['mtime'])) {
                $status = 'MODIFIED';
                $reason = 'diverged_from_baseline';
            } elseif ($s !== null && $t !== null && ($s['size'] !== $t['size'] || $s['mtime'] !== $t['mtime'])) {
                $status = 'CONFLICT';
                $reason = 'source_target_mismatch';
            } elseif ($s !== null && $t !== null) {
                $status = 'UNCHANGED_REUSABLE';
                $reason = 'fingerprint_match';
            }

            $referenced = in_array($path, $referencedPaths, true) ? 1 : 0;
            $items[] = [
                'path' => $path,
                'status' => $status,
                'reason' => $reason,
                'source_size' => $s['size'] ?? null,
                'target_size' => $t['size'] ?? null,
                'source_mtime' => $s['mtime'] ?? null,
                'target_mtime' => $t['mtime'] ?? null,
                'referenced' => $referenced,
                'confidence' => in_array($status, ['UNCHANGED_REUSABLE', 'NEW', 'MISSING_ON_TARGET'], true) ? 'high' : 'medium',
            ];
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }

        $this->storage->insertDeltaScanItems($scanId, $items);
        $this->storage->completeDeltaScan($scanId, $counts);

        return ['scan_id' => $scanId, 'counts' => $counts, 'total' => count($items)];
    }

    /** @return array<string,mixed> */
    public function buildTransferPlan(string $jobId, string $scanId, string $policy = 'balanced'): array
    {
        $planId = $this->storage->createTransferPlan($jobId, $scanId, $policy);
        $actions = [];
        foreach ($this->storage->deltaScanItems($scanId) as $item) {
            $status = (string) $item['status'];
            $action = 'MANUAL_REVIEW';
            $reason = 'ambiguous';
            $overwrite = 'never';
            $verify = 'fast';
            if ($status === 'UNCHANGED_REUSABLE') {
                $action = 'REUSE';
                $reason = 'reuse_if_same_size_mtime';
            } elseif (in_array($status, ['NEW', 'MISSING_ON_TARGET'], true)) {
                $action = 'COPY';
                $reason = 'required_on_target';
            } elseif ($status === 'MODIFIED') {
                $action = ((int) ($item['referenced'] ?? 0) === 1) ? 'REPLACE' : 'VERIFY';
                $reason = ((int) ($item['referenced'] ?? 0) === 1) ? 'replace_if_source_newer_and_referenced' : 'verify_before_replace';
                $overwrite = ((int) ($item['referenced'] ?? 0) === 1) ? 'if_newer' : 'never';
            } elseif ($status === 'TARGET_ONLY') {
                $action = 'SKIP';
                $reason = 'never_delete_target_only_without_explicit_policy';
            } elseif ($status === 'CONFLICT') {
                $action = 'QUARANTINE';
                $reason = 'quarantine_if_target_diverged';
                $verify = 'strong';
            }
            $actions[] = [
                'path' => (string) $item['path'],
                'action' => $action,
                'reason' => $reason,
                'confidence' => (string) $item['confidence'],
                'overwrite_policy' => $overwrite,
                'verification_mode' => $verify,
                'dependency_info' => ((int) ($item['referenced'] ?? 0) === 1) ? 'referenced_by_entity' : null,
            ];
        }

        $this->storage->insertTransferPlanItems($planId, $actions);

        return ['plan_id' => $planId, 'actions' => count($actions)];
    }

    /** @return array<string,mixed> */
    public function executeTransferPlan(string $planId, string $sourceRoot, string $targetRoot, bool $dryRun = false, int $limit = 1000): array
    {
        $applied = 0;
        $skipped = 0;
        $failed = 0;
        foreach ($this->storage->pendingTransferPlanItems($planId, $limit) as $item) {
            $path = (string) $item['path'];
            $action = (string) $item['action'];
            if (in_array($action, ['REUSE', 'SKIP', 'MANUAL_REVIEW', 'QUARANTINE', 'VERIFY'], true)) {
                $this->storage->markTransferPlanItem((int) $item['id'], 'completed', 'non_mutating');
                $skipped++;
                continue;
            }

            try {
                if (!$dryRun) {
                    $src = PathSafety::ensureInsideRoot($sourceRoot, $path);
                    $dst = PathSafety::ensureInsideRoot($targetRoot, $path);
                    if (!is_file($src)) {
                        throw new RuntimeException('source_missing');
                    }
                    copy($src, $dst);
                }
                $this->storage->markTransferPlanItem((int) $item['id'], 'completed', $dryRun ? 'dry_run' : 'copied');
                $applied++;
            } catch (\Throwable $e) {
                $this->storage->markTransferPlanItem((int) $item['id'], 'failed', $e->getMessage());
                $failed++;
            }
        }

        return ['plan_id' => $planId, 'applied' => $applied, 'skipped' => $skipped, 'failed' => $failed, 'dry_run' => $dryRun];
    }

    /** @param array<int,array<string,mixed>> $references */
    public function reconcileReferences(string $scanId, array $references): array
    {
        $missing = 0;
        $issues = [];
        $items = $this->storage->deltaScanItemsMap($scanId);
        foreach ($references as $ref) {
            $path = PathSafety::normalizeRelative((string) ($ref['path'] ?? ''));
            $meta = $items[$path] ?? null;
            if ($meta === null || in_array((string) ($meta['status'] ?? ''), ['MISSING_ON_TARGET', 'NEW'], true)) {
                $missing++;
                $issues[] = ['type' => 'REFERENCED_FILE_MISSING', 'path' => $path, 'entity_type' => (string) ($ref['entity_type'] ?? 'unknown'), 'severity' => 'high'];
            }
        }

        foreach ($issues as $issue) {
            $this->storage->saveReconciliationIssue((string) $scanId, $issue['type'], $issue['path'], $issue['entity_type'], $issue['severity'], $issue);
        }

        return ['scan_id' => $scanId, 'issues' => $issues, 'missing_referenced_files' => $missing];
    }

    public function cutoverCheck(string $jobId, string $scanId, int $pass = 1): array
    {
        $counts = $this->storage->deltaScanCounts($scanId);
        $issues = $this->storage->reconciliationIssuesByScan($scanId);
        $remaining = (int) (($counts['NEW'] ?? 0) + ($counts['MODIFIED'] ?? 0) + ($counts['MISSING_ON_TARGET'] ?? 0));
        $missingRef = 0;
        foreach ($issues as $issue) {
            if ((string) $issue['issue_type'] === 'REFERENCED_FILE_MISSING') {
                $missingRef++;
            }
        }

        $status = 'safe';
        if ($missingRef > 0) {
            $status = 'blocked';
        } elseif ((int) ($counts['CONFLICT'] ?? 0) > 0 || $remaining > 100) {
            $status = 'risky';
        }

        $report = [
            'job_id' => $jobId,
            'scan_id' => $scanId,
            'mode' => 'cutover',
            'pass' => $pass,
            'remaining_delta_count' => $remaining,
            'remaining_referenced_missing_files' => $missingRef,
            'unresolved_conflicts' => (int) ($counts['CONFLICT'] ?? 0),
            'estimated_readiness' => $status,
            'quiet_period_detected' => $remaining === 0,
            'cutover_verdict' => $status,
        ];
        $this->storage->saveCutoverReadinessReport($jobId, $scanId, $report);

        return $report;
    }

    /** @return iterable<int,string> */
    private function walk(string $root): iterable
    {
        $rootReal = realpath($root);
        if ($rootReal === false) {
            throw new RuntimeException('invalid_root');
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootReal, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isLink() || !$fileInfo->isFile()) {
                continue;
            }
            yield $fileInfo->getPathname();
        }
    }

    /** @return array<string,array{size:int,mtime:int}> */
    private function treeMap(string $root): array
    {
        $map = [];
        $rootReal = realpath($root);
        if ($rootReal === false) {
            return $map;
        }
        foreach ($this->walk($rootReal) as $file) {
            $relative = PathSafety::normalizeRelative(substr($file, strlen($rootReal) + 1));
            $map[$relative] = ['size' => filesize($file) ?: 0, 'mtime' => filemtime($file) ?: 0];
        }
        ksort($map);

        return $map;
    }
}
