# Cutover State Machine

Statuses:
- draft
- planned
- awaiting_approval
- approved
- preflight_running
- preflight_failed
- ready_for_go_live
- go_live_in_progress
- freeze_activated
- final_delta_running
- final_reconciliation_running
- switching
- post_switch_validation
- live
- rollback_recommended
- rollback_in_progress
- rolled_back
- failed
- aborted

State transitions are enforced by `CutoverStateMachine` and persisted as `cutover_events`.
