# Go-Live Runbook

1. `cutover:plan` — create plan and policy.
2. `cutover:window:check` — validate active cutover window.
3. `cutover:readiness` — evaluate mandatory gate checks.
4. `cutover:approve` (3 scopes) — migration_operator, technical_owner, business_owner.
5. `cutover:go-live` — executes freeze/delta/reconciliation/switch/post-switch validation pipeline.
6. `cutover:status` and `cutover:report` — monitor and export final report.

Use `--dry-run` for rehearsal and `--strict` to block unsafe continuation.
