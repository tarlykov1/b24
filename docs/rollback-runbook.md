# Rollback Runbook

1. `cutover:rollback:prepare --cutover-id=<id>`
2. Validate rollback window and operator confirmations.
3. `cutover:rollback:execute --cutover-id=<id>`
4. Confirm `rolled_back` in `cutover:status`.

Rollback is reported as completed only after orchestrator journal stage completion and state transition.
