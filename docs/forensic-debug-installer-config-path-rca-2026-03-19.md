# Forensic debug report: installer/runtime config path split-brain

Date: 2026-03-19
Scope: Bitrix24 migration service / migration-module

## Summary
Critical config-path split-brain is confirmed in production code:

- Runtime loader (`DbConfig`) reads **only** canonical artifact `config/generated-install-config.json`.
- Web installer UI action "Save canonical config" calls `/install/save-canonical-config`.
- Backend handler for `/install/save-canonical-config` writes to **`config/runtime.install.json`**.

Result: installer returns success for save operation, but runtime health/readiness still fails with `mysql_config_missing` because runtime never reads `runtime.install.json`.

## Evidence
1. Canonical runtime read path is hardcoded:
   - `DbConfig::CANONICAL_ARTIFACT = 'config/generated-install-config.json'`.
   - `DbConfig::loadArtifact()` reads only that path via `canonicalPath()` and does not check legacy aliases.
2. Runtime health/readiness use `DbConfig::fromRuntimeSources([], ...)` and therefore rely on the canonical artifact when no valid override/env is present.
3. Installer UI label/action claims canonical save via `/install/save-canonical-config`.
4. API route handler writes different files depending on route:
   - `/install/generate-config` default output: `config/generated-install-config.json`.
   - `/install/save-canonical-config` default output: `config/runtime.install.json`.
5. docs/tests baseline canonical artifact as `config/generated-install-config.json`, while one acceptance checklist records save into `config/runtime.install.json`, demonstrating drift.

## Root cause
Primary root cause:

- **Route-level path divergence bug** in installer API: endpoint named and used as canonical-save (`/install/save-canonical-config`) persists to non-canonical file (`config/runtime.install.json`), while runtime loads only `config/generated-install-config.json`.

Contributing factors:

- No backward-compatible read fallback in `DbConfig::loadArtifact()` for legacy aliases.
- No integration tests validating end-to-end installer-save -> runtime-health green path for `/install/save-canonical-config`.
- Naming mismatch (`save-canonical-config` vs actual target file) hides defect and produces false-positive UX.

## User-visible impact
- User runs installer and receives success on config save.
- `/web/api.php/health` and `/web/api.php/ready` can still fail with `system_check_failed` and nested `mysql_config_missing` due to empty runtime DB credentials.
- UI appears correctly configured, but runtime remains blocked until manual artifact copy/create at canonical path.

## Fix plan (minimal safe)
1. Declare and enforce single canonical artifact path:
   - `config/generated-install-config.json`.
2. Change `/install/save-canonical-config` default write target to canonical path (same as `/install/generate-config`).
3. Add backward-compatible read fallback in `DbConfig::loadArtifact()`:
   - read canonical first;
   - if missing, attempt legacy aliases (`config/runtime.install.json`, optionally `config/runtime_install.json`), emit deprecation signal/log, and prefer canonical if both exist.
4. Normalize installer routes:
   - either keep both routes as aliases to same canonical write behavior, or deprecate one explicitly with stable warning payload.
5. Protect against secret masking persistence regression:
   - keep redaction output only in response payload; do not alter stored file content.
6. Add tests for path resolution precedence and installer/runtime end-to-end behavior.
7. Update docs/checklists to remove non-canonical artifact mentions.

## Exact code changes (expected)
- `apps/migration-module/ui/admin/api.php`
  - `/install/save-canonical-config` default output path -> `config/generated-install-config.json`.
  - optional deprecation metadata for legacy endpoint alias.
- `apps/migration-module/src/Support/DbConfig.php`
  - add explicit legacy read fallback list with deterministic precedence.
  - keep canonical constant unchanged.
- `tests/...`
  - add endpoint-level/integration tests for save path and health/readiness behavior.
- `docs/...`
  - align installer docs/checklists on canonical artifact only; mark legacy names deprecated.

## Tests to add
1. Unit: `DbConfig` artifact resolution precedence
   - canonical present + legacy present -> canonical wins.
   - canonical absent + legacy present -> legacy accepted (temporary compatibility).
   - invalid JSON in legacy -> ignored with empty fallback.
2. Integration: installer route save
   - POST `/install/save-canonical-config` creates/updates `config/generated-install-config.json` (not `runtime.install.json`).
3. Integration: end-to-end install-to-health
   - save config via installer route; then call `/health` and `/ready`; expect `ok=true` when DB reachable.
4. Regression: password persistence
   - persisted artifact stores real password; API response redacts password.
5. Docs consistency check (optional lint)
   - fail if docs claim canonical save to non-canonical filename.

## Regression risks
- Existing deployments/scripts that intentionally read/write `config/runtime.install.json` may rely on legacy filename.
- If fallback precedence is implemented incorrectly, env/override ordering could accidentally change.
- Route behavior changes can affect automation pinned to old endpoint semantics.

Mitigation:
- Keep route alias but canonicalize output.
- Keep temporary read fallback with clear deprecation period and migration note.

## Final verdict
**NOT accepted** as-is (acceptance blocker).

Reason: installer reports successful canonical save while runtime cannot boot from that saved artifact without manual file copy to canonical path.
