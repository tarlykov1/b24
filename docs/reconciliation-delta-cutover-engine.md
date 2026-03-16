# Reconciliation, Delta Sync & Cutover Readiness Engine

## New commands

- `migration reconcile <job_id> --entity=users|crm|tasks|files --strategy=fast|balanced|deep --limit=100 --offset=0 --sample=1 --rate-limit=25`
- `migration delta-sync <job_id> --since=2026-03-01 --entity=tasks --batch-size=100 --rate-limit=25 [--dry-run]`
- `migration cutover-check <job_id> [--sample=20]`
- `migration cutover-simulate <job_id> [--entity=tasks --since=... --batch-size=50 --sample=20 --rate-limit=25]`

## Storage additions (SQLite)

- `reconciliation_results`
- `delta_queue`
- `cutover_reports`

## Runtime modules

- `MigrationModule\Reconciliation\ReconciliationEngine`
- `MigrationModule\Delta\DeltaSyncEngine`
- `MigrationModule\Cutover\CutoverReadinessAnalyzer`

## Reports

Generated in:

- `reports/reconciliation`
- `reports/delta`
- `reports/cutover`

Each command emits JSON output and writes JSON + HTML report artifacts.

## Safety model

- Batch processing via `--limit` / `--batch-size`
- Explicit CLI rate limiting via `--rate-limit`
- Sample ratio support in reconciliation via `--sample`
- Read-safe execution over source API adapters
