# Migration Runtime

## Runtime storage
SQLite schema is initialized from `db/prototype_schema.sql` and includes:
- `jobs`, `queue`, `entity_map`, `user_map`, `checkpoint`, `logs`, `diff`, `integrity_issues`, `state`
- plus deterministic execution and delta-support tables.

## Deterministic execution guarantees
- Plan hash + stable plan representation.
- Dependency graph and stable batching.
- Checkpoint save/load and resume.
- Retry and replay protection.
- Idempotent upsert-based execution model.
