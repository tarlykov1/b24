# Continuous Migration & Data Synchronization Platform

This design evolves the migration framework from one-time execution into a persistent synchronization platform.

## Service Modes

- `event-driven`: consume REST/webhook/queue events.
- `scheduled-delta`: periodic timestamp/hash scans.
- `hybrid` (default): event pipeline + scheduled drift backstop.

## Supported Mobility Patterns

- Old Bitrix → New Bitrix (post-cutover delta)
- Staging → Production promotion sync
- Primary → Standby DR replication
- Controlled coexistence during gradual migration
- Rollback-ready reverse replay from ledger

## Core Data Model

### `sync_state`
Stores authoritative per-entity state:

- `last_synced_at`
- `last_hash`
- `source_version`
- `target_version`
- `sync_state`
- `direction`
- `mode`

### `sync_conflicts`
Stores conflict evidence + operator outcome.

Conflict types:

- simultaneous edits
- deletion/update races
- field divergence
- relation mismatch

Resolution strategies:

- `source_priority`
- `target_priority`
- `last_write_wins`
- `manual_resolution`

### `sync_drift`
Persistent drift register for:

- missing entity
- extra entity
- field mismatch
- relation mismatch
- file mismatch

### `sync_ledger`
Immutable operation timeline for rollback, forensics, and audit.

### `sync_metrics`
Operational counters and health score; mapped to Prometheus-style metrics:

- `sync_operations_total`
- `sync_errors_total`
- `sync_conflicts_total`
- `sync_drift_total`
- `replication_lag_seconds`

## Runtime Components

- `sync_coordinator`: schedules scans, windows, and dispatch.
- `sync_workers`: parallel apply workers with rate-limits.
- `conflict_resolver`: policy and manual queue.
- `metrics_collector`: lag/health/SLO emission.

## CLI Contract

- `migration sync:start <job_id>`
- `migration sync:stop <job_id>`
- `migration sync:status <job_id>`
- `migration sync:verify <job_id>`
- `migration sync:conflicts <job_id>`
- `migration sync:resolve <job_id> <conflict_id> [strategy]`
- `migration sync:drift <job_id>`
- `migration sync:policy <job_id>`
- `migration sync:service <job_id>`
- `migration dr:status <job_id>`

## Operational Safety

- Controlled sync windows are enforced by policy (`sync.window`).
- Hybrid mode ensures event-loss recovery via scheduled diff.
- Queue lag influences throttling and worker fan-out.
- Every mutation can be reconstructed from `sync_ledger`.

## Web Control Center Extension (Planned)

The Control Center should add:

- Sync dashboard (status, lag, health, backlog)
- Drift heatmap (entity/type/severity)
- Conflict resolution panel
- Sync timeline from ledger events
