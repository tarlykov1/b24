# Prototype Runtime Contract

## Modes
- `dry-run`: строит план и diff, не пишет target.
- `execute`: выполняет очередь, пишет mapping/checkpoint/log/state.
- `resume`: продолжает обработку `pending/retry` после checkpoint.
- `verify-only`: команда `verify`, сравнивает source snapshot c migration state.

## Self-healing (prototype)
- transient error -> `retry` + повторная очередь;
- permanent error -> `integrity_issues`;
- healing summary доступен через `report`/`status`.

## Storage
SQLite tables: `jobs`, `queue`, `entity_map`, `user_map`, `logs`, `checkpoint`, `diff`, `integrity_issues`, `state`.
