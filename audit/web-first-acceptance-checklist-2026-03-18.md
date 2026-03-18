# Web-first acceptance checklist (2026-03-18)

| Step | Status | Evidence |
|---|---|---|
| Environment check (browser installer) | PASS | `/install/environment-check` now validates runtime prerequisites including `pdo_mysql` and `curl`. |
| Config save (canonical runtime config) | PASS | Installer saves `config/runtime.install.json` via `/install/save-canonical-config`. |
| MySQL schema init | PASS | `/install/check-connection` performs live TCP/auth/schema/write probes before `/install/init-schema`. |
| Create job | PASS | Admin UI uses canonical `/runtime/jobs/create`. |
| Validate | PASS | Admin UI lifecycle action calls `/runtime/jobs/{jobId}/action` with `validate`. |
| Dry-run | PASS | Admin UI lifecycle action calls canonical runtime action endpoint. |
| Execute | PASS | Admin UI lifecycle action calls canonical runtime action endpoint. |
| Pause/resume | PASS | Admin UI lifecycle controls call canonical runtime action endpoint (`pause` / `resume`). |
| Verify | PASS | Verify action persists a report row in `cutover_reports` and exposes it via canonical runtime endpoints. |
| Report view/download | PASS | Admin UI reads `/runtime/jobs/{jobId}/reports`; download uses `/runtime/jobs/{jobId}/reports/{reportId}/download`. |
| Legacy job flows produce ambiguity | PASS (mitigated) | Legacy `/jobs` and `/control-center/jobs` routes now return `deprecated_endpoint` and point to runtime routes. |
| SSH/manual console required | PASS | None required for standard installer + job lifecycle path; SSH is not required for accepted flow. |

## Classification

**true web-first** for installer + runtime lifecycle operations covered above.
