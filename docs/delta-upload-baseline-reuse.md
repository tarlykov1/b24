# Delta Sync Engine with Baseline `/upload` Reuse

## Operator flow
1. Register imported baseline: `baseline:index --job-id --source-root --target-root --verification-mode=fast|balanced|strict`.
2. Observe registry: `baseline:status --baseline-id` and `baseline:verify --baseline-id`.
3. Produce deterministic file delta: `delta:scan --baseline-id --source-root --target-root [--refs-json=references.json]`.
4. Build reusable transfer plan: `delta:plan --scan-id --policy=balanced`.
5. Execute or resume plan: `delta:execute --plan-id` / `delta:resume --plan-id`.
6. Inspect scan summary: `delta:report --scan-id`.
7. Run final cutover readiness check: `delta:cutover-check --scan-id`.

## Classification model
Delta scan writes auditable statuses per file:
- `NEW`
- `MODIFIED`
- `MISSING_ON_TARGET`
- `TARGET_ONLY`
- `CONFLICT`
- `UNCHANGED_REUSABLE`
- `UNVERIFIED`

## Mutation policy
- `TARGET_ONLY` is preserved by default.
- `CONFLICT` goes to `QUARANTINE` action.
- `MODIFIED` is `REPLACE` only when referenced; otherwise `VERIFY`.
- `UNCHANGED_REUSABLE` is action `REUSE`.
- Any copy/replace operation is path-normalized and root-bounded.

## Persistence model
SQLite runtime tables:
- `upload_baseline_snapshots`
- `upload_baseline_files`
- `upload_delta_scans`
- `upload_delta_scan_items`
- `upload_transfer_plans`
- `upload_transfer_plan_items`
- `upload_reconciliation_issues`
- `upload_cutover_readiness_reports`

## Cutover readiness verdict
`delta:cutover-check` returns:
- remaining delta count
- referenced missing files
- unresolved conflicts
- quiet period heuristic
- verdict: `safe` / `risky` / `blocked`
