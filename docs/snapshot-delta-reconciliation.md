# Snapshot/Delta/Reconciliation Consistency Model

## Architecture plan

1. Snapshot boundary фиксируется через `SnapshotConsistencyService`.
2. Baseline wave работает только с данными `<= source_cutoff_time`.
3. Delta planner/extractor выбирает изменения `> source_cutoff_time`.
4. Conflict engine + sync policy engine принимают explainable решения.
5. Reconciliation queue закрывает late-bound dependencies/files/relations.
6. Relation/file verification блокирует healthy-статус до полного восстановления связей.

## Snapshot model
- `snapshot_id`
- `snapshot_started_at`
- `source_cutoff_time`
- `per_entity_watermark`
- `per_module_cursor`
- `snapshot_status`

## Watermark model
По каждому entity type:
- `last_extracted_source_marker`
- `last_reconciled_source_marker`
- `last_verified_source_marker`
- `last_target_sync_marker`

Поддерживаемые маркеры:
- timestamp
- incremental id
- page cursor
- composite cursor

## Conflict lifecycle
1. Detect (`ConflictDetectionEngine`).
2. Classify (type/severity/options/manual_required).
3. Policy gate (`SyncPolicyEngine`).
4. Route to auto-apply/reconciliation/manual review.
5. Persist conflict + explicit resolution.

## Reconciliation flow
- Queue reasons: unresolved refs, orphan dependencies, delayed files, incomplete mappings.
- Retry metadata: reason/dependency/retry_count/last_attempt/next_attempt/escalation.
- After dependency repair item can move from `dependency_blocked` to `reconciled` and `verified`.

## Remaining limitations
- Delete propagation intentionally conservative by default (manual-first).
- No transactional snapshot at source; logical snapshot boundary is used.
