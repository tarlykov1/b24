# Migration Control Center

## Overview
The Migration Control Center is an operator-facing layer built on top of the existing migration runtime and SQLite prototype storage.

## Operator Workflow
1. Open `/migration/dashboard` for live metrics (1-2 second refresh cadence).
2. Investigate entity drift with `/migration/diff/{entity}/{id}`.
3. Resolve conflicts from `/migration/conflicts` and `/migration/conflicts/resolve`.
4. Repair integrity issues via `/migration/integrity` and `/migration/integrity/repair`.
5. Use manual intervention commands for pause/resume/retry/repair/diff from CLI.

## Live Monitoring
Dashboard metrics include:
- `total_entities`
- `processed_entities`
- `failed_entities`
- `retry_queue`
- `current_stage`
- `throughput_per_minute`
- `estimated_completion_time`

The timeline reports stages: validation, planning, execution, self-healing, verification, complete.

## Diff Analysis
The diff engine supports:
- users
- crm_leads
- crm_deals
- crm_contacts
- tasks
- files

Detected categories:
- missing fields
- changed values
- missing relations
- stage mismatches
- user mapping differences
- file hash mismatches

Default mode is read-only. UI actions are explicit: accept source, accept target, manual edit, ignore.

## Conflict Resolution
Conflict examples:
- duplicate IDs
- missing users
- stage mapping errors
- deleted references
- permission conflicts

Available actions:
- remap
- skip
- merge
- create new
- assign system user
- manual edit

Supports single, batch, and auto policy based resolution. Pagination defaults to `limit=100`.

## Integrity Repair Process
Repair center surfaces integrity issues and proposes actions such as:
- rebuild relation
- re-sync entity
- re-download file
- re-assign user
- re-map stage

Safety guarantees:
- repair requires confirmation
- batch repair capped at 1000 records
- destructive intervention should be paused first

## Manual Intervention Commands
CLI command set:
- `migration pause`
- `migration resume`
- `migration retry <entity_type>:<source_id>`
- `migration repair`
- `migration diff <entity_type>:<source_id>`

## Logging
Control Center writes additional log levels into `logs.level`:
- `operator_action`
- `conflict_resolution`
- `integrity_repair`
- `manual_override`

## Continuous Sync Dashboard Extension

The Control Center now includes a planned Sync workspace backed by `sync_*` tables and CLI services:

- **Sync Dashboard**: status, sync mode, direction, worker health, replication lag, queue backlog.
- **Drift Heatmap**: grouped by `entity_type`, `drift_category`, `severity`.
- **Conflict Resolution UI**: list/resolve rows from `sync_conflicts`.
- **Sync Timeline**: render `sync_ledger` chronologically for audit/rollback analysis.

Metrics source: `/metrics/sync` exporter should map counters from `sync_metrics`.
