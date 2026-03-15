# Cutover Planning & Go-Live Orchestrator

## Architecture

New module layers:

- `CutoverPlanService` — versioned, auditable cutover plan lifecycle.
- `GoLiveReadinessEngine` — readiness score, hard/soft blockers, recommendation.
- `FreezePolicyManager` — technical/operational freeze with allowlist and exception trail.
- `FinalDeltaSyncOrchestrator` — final delta execution modes and priority ordering.
- `CutoverRehearsalEngine` — simulation, bottleneck prediction, confidence score, what-if.
- `PreflightCheckRunner` — PASS / PASS_WITH_WARNINGS / FAIL with explicit override trail.
- `GoLiveStateMachine` — controlled switch-over as deterministic finite state machine.
- `SmokeTestRunner` — post-switch acceptance with rollback candidacy rules.
- `StabilizationManager` — 15m/1h/24h/3d hypercare tracking.
- `RollbackCoordinator` — full/partial/fallback/stabilization decision model.
- `RunbookGenerator` — versioned production runbook payload (json/pdf/docx export-ready).
- `CommunicationTemplateEngine` — templated operational communication orchestration.
- `WindowRecommendationService` — low-activity window recommendation (local heuristics).
- `CutoverAuditTrail` — tamper-evident hash-chain event log.
- `GoLiveOrchestrator` — end-to-end preflight→delta→smoke→rollback decision flow.

## Go-live state machine

States:

`draft -> planned -> awaiting-approvals -> approved -> rehearsal-ready -> preflight-running -> (preflight-failed|freeze-pending) -> freeze-active -> delta-sync-running -> validation-running -> switch-pending -> switching -> smoke-test-running -> stabilization -> (completed|completed-with-warnings)`

Rollback branch:

`freeze-active|delta-sync-running|validation-running|switch-pending|switching|smoke-test-running|stabilization -> rollback-pending -> rolling-back -> rolled-back`

Terminal states:

`completed`, `completed-with-warnings`, `rolled-back`, `aborted`.

## Contracts

Primary DTO: `cutover command center` (`/cutover`) includes timeline, readiness, blockers, approvals, freeze status, delta progress, smoke status, rollback panel, runbook tracker, communication preview, and event log.

CLI:

- `cutover:plan`
- `cutover:readiness`
- `cutover:preflight`
- `cutover:delta`
- `cutover:rehearsal`
- `cutover:runbook`
- `cutover:state-graph`
- `cutover:comm`
- `cutover:window`
- `cutover:orchestrate`

## DB persistence model

Added cutover tables in `db/migration_schema.sql`:

`cutover_plan`, `cutover_plan_version`, `cutover_approval`, `cutover_phase_run`, `cutover_event_log`, `cutover_blocker`, `cutover_readiness_snapshot`, `cutover_freeze_exception`, `cutover_preflight_result`, `cutover_smoke_test_result`, `cutover_stabilization_issue`, `cutover_rollback_run`, `cutover_runbook`, `cutover_comm_template`, `cutover_window_recommendation`.

## Readiness scoring algorithm

Starts from 100 and applies weighted penalties for queue size, integrity issues, mapping conflicts, worker health, retry storm, failed dry-run/verify, delta-vs-window mismatch, source load risk, pending decisions/approvals, parity gaps, and wave completeness.

Output:

- score
- hard blockers
- soft blockers
- warnings
- must-fix list
- acceptable known issues
- recommendation (`not_ready | ready_with_warnings | ready | ready_only_for_phased_cutover`)

## Runbook generation

`RunbookGenerator` produces minute-by-minute trackable sections:

- objective/scope/roles
- pre-start and freeze checklists
- final sync and switch actions
- smoke tests / acceptance
- rollback triggers and steps
- stabilization actions
- communication timeline + escalation

## Smoke and rollback logic

- critical smoke fail -> rollback candidate
- major fail -> stabilization or partial rollback
- minor fail -> go-live with issue registration

Rollback coordinator explicitly marks:

- technically possible
- risky
- impossible (stabilization only)

## UI

New page: **Cutover Command Center** (`/cutover`) with readiness markers, blockers/warnings, approval panel, freeze status, delta ETA, and minute-by-minute runbook tracker.
