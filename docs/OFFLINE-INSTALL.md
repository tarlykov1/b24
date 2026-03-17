# OFFLINE-INSTALL

## Target scenario
Portable archive on new Bitrix server without internet.

## Deployment flow
1. Extract to `/b24_migration`.
2. Open web entrypoint.
3. If DB config is missing: complete installer.
4. Verify with `deployment:check`.
5. Initialize schema (installer `init-schema`).
6. Save generated config (`config/generated-install-config.json`).
7. Continue in UI / audit / migration tools.

## Preconfigured mode
If `DB_*` variables are preconfigured, installer is skipped and runtime starts directly.

## Canonical DB config artifact
`config/generated-install-config.json` with top-level `mysql` object.
