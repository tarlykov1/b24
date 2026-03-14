# Migration Operations Console

Production-oriented web layer for migration runtime/orchestrator/control-center with real-time observability and operator workflows.

## Architecture

- **Frontend SPA**: `apps/migration-console` (React + TypeScript + React Router + Zustand + React Query + Recharts).
- **Backend UI API gateway**: `apps/migration-module/ui/admin/api.php`.
- **Typed UI service contracts**: `apps/migration-module/src/Infrastructure/Http/OperationsConsoleApi.php`.
- **Realtime transport**: Server-Sent Events endpoint `GET /stream?topic=dashboard|workers|logs`.
- **Graceful degradation**: when SSE fails, frontend keeps polling with React Query (`refetchInterval`).
- **Feature flags / roles contract**: `GET /meta` used by frontend shell and module guards.

## Implemented screens

- Global Dashboard
- Migration Jobs (+ job card with timeline/overview tab data)
- Dependency Graph View
- Error Heatmap
- CRM Mapping Studio
- Workers Stream Monitor
- Real-Time Logs Console
- Conflict Resolution Center
- Integrity Repair Center
- Diff Explorer
- Replay / Resume / Incremental Sync Center
- System Health / Throughput / Queue Pressure

## Backend endpoints

Read endpoints:

`/meta`, `/dashboard`, `/jobs`, `/jobs/details`, `/graph`, `/heatmap`, `/mapping`, `/workers`, `/logs`, `/conflicts`, `/integrity`, `/diff`, `/replay-preview`, `/system-health`, `/stream`.

Action contracts:

`POST /jobs/action`, `POST /workers/action`, `POST /mapping/action`, `POST /integrity/action`, `POST /conflicts/action`, `POST /replay/action`.

All list endpoints use server-side pagination (`limit`, `offset`) and filtering query params (`jobId`, `status`, `severity`, etc.).

## Runtime compatibility and integration model

- Existing CLI/runtime scenarios remain untouched (`bin/migration-module`).
- UI action endpoints are stable contracts; runtime binding can be attached behind feature flags without frontend refactor.
- API supports mock/fallback mode when SQLite prototype DB is absent.

## Run

### API (PHP)

```bash
php -S 0.0.0.0:8080 -t apps/migration-module/ui/admin
```

### Frontend

```bash
cd apps/migration-console
npm install
npm run dev
```

Optional env:

```bash
VITE_MIGRATION_API_BASE=http://localhost:8080/api.php
```

## Next stage hooks

- Bind action contracts to runtime orchestrator controls (worker restart, queue quarantine, repair batch execution).
- Replace synthetic graph/heatmap generators with storage-backed analytics projections.
- Add RBAC enforcement on API side using existing platform auth/session.
