# Architecture

The runtime is intentionally scoped for a single migration project profile (Bitrix24 on‑prem → Bitrix24 on‑prem clean target).

## Active runtime modules
- Deterministic execution, checkpoint/resume, retry, replay protection.
- Queue + mapping + state persistence in SQLite.
- Audit/discovery and verification/reporting.
- Delta planning/execution.

## Partial modules
- Deep relation verification beyond core links.
- File reference verification beyond metadata/path checks.
- Advanced delta conflict policy handling.

## Future modules
- Upgrade manager and platform upgrade framework.
- Enterprise reconciliation platform.
- Complex policy orchestration and advanced snapshot orchestration.

Future modules are isolated from the runtime CLI.
