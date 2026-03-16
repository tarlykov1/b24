# Cutover Orchestrator

`cutover:*` commands now run a persisted orchestration control-plane with SQLite-backed state.

## What is automated
- state-machine transitions with deterministic transition validation;
- readiness checks persistence by groups/gates;
- approval records and required-approval evaluation;
- stage journaling with execution keys and idempotent `cutover:go-live` live-state return;
- cutover report artifact persistence.

## What is manual
- freeze policy is currently `operator_confirmed_manual_freeze`;
- switch executor is currently `manual_app_config_switch` with runbook confirmation semantics;
- rollback execution is orchestrated and journaled, but still manual-step based.
