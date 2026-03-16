# Cutover Finalization with Delta Freeze Window

## What freeze window means in this runtime
The freeze window is a **durable operator-controlled state machine** used for final migration sign-off.

Honest behavior:
- This runtime **does not claim a global Bitrix hard lock**.
- It performs mutation detection and applies configured cutover policy (`advisory_freeze`, `strict_freeze`, `detect_only`).
- `strict_freeze` blocks go-live flow when protected-domain mutations are detected.

## Production prerequisites
- Source and target Bitrix24 on-premise endpoints reachable.
- Source/target MySQL reachable.
- Baseline migration and previous delta sync completed.
- `/upload` bulk copy may be performed separately.
- Mapping completeness and integrity checks acceptable for protected scopes.

## Rehearsal procedure (detect-only)
1. `cutover:prepare`
2. `cutover:readiness`
3. `cutover:arm --mode=detect_only`
4. `cutover:freeze:start --mode=detect_only`
5. `cutover:delta:final`
6. `cutover:verify`
7. `cutover:verdict`

## Production cutover procedure
1. Prepare session with source/target instance IDs.
2. Run readiness checks and resolve `blocked` findings.
3. Arm freeze policy (`advisory_freeze` or `strict_freeze`) and protected domains.
4. Start freeze; ingest mutation evidence collected by DB/event probes.
5. Run final delta until complete.
6. Run cutover verification.
7. Evaluate deterministic verdict.
8. Complete cutover only when approved (or explicit operator override path is documented and audited).

## Abort/resume
- `cutover:abort` stores explicit abort reason and leaves persisted truth state.
- `cutover:resume` is allowed only from `blocked` state and returns to `armed`.
- No implicit reset/new job IDs are generated during resume.

## Command contract
All commands return JSON and non-zero exit on runtime errors.

- `cutover:prepare --job-id --freeze-id --source-instance-id --target-instance-id --actor [--meta-json]`
- `cutover:readiness --job-id --freeze-id --signals-json`
- `cutover:arm --job-id --freeze-id --mode --protected-domains --actor`
- `cutover:freeze:start --job-id --freeze-id --mode --mutations-json --actor`
- `cutover:freeze:status --freeze-id`
- `cutover:delta:final --job-id --freeze-id --baseline-json --actor`
- `cutover:verify --job-id --freeze-id --signals-json --actor`
- `cutover:verdict --freeze-id --context-json`
- `cutover:abort --freeze-id --actor --reason`
- `cutover:resume --freeze-id --actor`
- `cutover:complete --freeze-id --actor [--override=1 --override-json]`

## Example JSON outputs
### Readiness
```json
{
  "freeze_window_id": "freeze_01",
  "state": "prepared",
  "readiness": {
    "status": "pass_with_warnings",
    "allow_freeze_activation": true,
    "checks": [
      {
        "code": "rate_limit_risk",
        "severity": "warning",
        "status": "failed",
        "subsystem": "throttling",
        "evidence": "risk=81",
        "recommended_action": "resolve_rate_limit_risk",
        "freeze_activation_allowed": true
      }
    ]
  }
}
```

### Freeze status
```json
{
  "freeze_window_id": "freeze_01",
  "state": "freeze_active",
  "blocker_count": 0,
  "resumable": true,
  "mutation_summary": {"total": 5, "blocking": 0},
  "honesty_note": "Freeze mode enforcement is policy-driven mutation detection; global source write lock is not guaranteed by this service."
}
```

### Mutation detection summary
```json
{
  "analysis": {
    "mode": "strict_freeze",
    "mutations_detected_total": 3,
    "blocking_mutations": 2,
    "counts_by_entity_type": {"crm": 2, "tasks": 1},
    "freeze_policy_result": "blocked"
  }
}
```

### Verification result
```json
{
  "verification": {
    "color": "yellow",
    "failed_checks": [],
    "warnings": ["sampled_record_equivalence"]
  }
}
```

### Final verdict
```json
{
  "rationale": {
    "verdict": "operator_review_required",
    "reasons": ["yellow_needs_explicit_override"],
    "blockers": [],
    "warnings": ["verification_yellow"],
    "recommended_next_actions": ["review_blockers", "rerun_verification"],
    "override_allowed": true,
    "override_risk_level": "medium"
  }
}
```

## Troubleshooting matrix
- `invalid_freeze_transition:*`: wrong phase ordering. Use `cutover:freeze:status` and continue from persisted state.
- `delta_not_allowed_in_state:*`: freeze not active/armed correctly.
- `verification_not_allowed`: final sync phase not reached.
- `resume_allowed_only_from_blocked`: resume command misuse.

## Limitations and known risks
- Global source write-lock is not enforced by runtime itself.
- Mutation evidence quality depends on upstream probes (DB timestamps/events/snapshots) passed into `--mutations-json`.
- `target_portal_smoke_sanity` is signal-based and should be wired to real smoke scripts in production.
