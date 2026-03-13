# Bitrix24 Enterprise Migration Toolkit

This repository contains a resumable Bitrix24 → Bitrix24 migration toolkit with safe throttled processing, checkpoint-based recovery, sync preview, and admin controls.

## What is implemented in this stage

- Two run modes: `initial_import` and `sync`.
- Sync preview/dry-run diff before write operations.
- Job lifecycle controls: `Start / Pause / Resume / Stop` with persisted state.
- ID mapping layer with source ID preservation attempt and conflict-safe remap.
- Reference resolution + post-write integrity checks through mapping.
- Inactive users cutoff policy with task strategy options:
  - delete tasks,
  - reassign to system account,
  - keep user.
- Queue + deduplication for idempotent writes.
- Checkpoints for stage/batch resume.
- Throttling defaults with batch pauses and backoff calculation.
- Dual logging model:
  - technical logs (system sink),
  - migration journal (UI-ready records).
- Runtime metrics:
  - processed entities,
  - request count,
  - batch counters,
  - batch duration aggregate.

## Main modules

- `apps/export-agent` — source-side extract logic contracts.
- `apps/migration-module` — migration orchestration, queueing, mapping, diff, controls, verification.
- `db/migration_schema.sql` — schema for jobs, mapping, queue, logs, checkpoints, diffs.

## Admin UI

`apps/migration-module/ui/admin/index.php` provides a practical monitoring/control layout with:
- mode + cutoff settings,
- Start/Pause/Resume/Stop controls,
- progress counters,
- sync preview section,
- event journal grid.

## Next iteration ideas

- Plug real Bitrix REST adapters for source/target.
- Replace JSON repository with DB-backed repositories.
- Add worker daemon for asynchronous queue processing.
- Add richer relation validators for CRM/files/custom fields.
