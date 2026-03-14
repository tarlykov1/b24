# Migration Operations Console

Production-oriented web layer for migration runtime/orchestrator/control-center.

## Architecture

- **Frontend**: `apps/migration-console` (React + TypeScript + React Router + Zustand + React Query + Recharts).
- **Backend UI API**: `apps/migration-module/ui/admin/api.php`.
- **UI service contracts**: `apps/migration-module/src/Infrastructure/Http/OperationsConsoleApi.php`.
- **Realtime transport**: Server-Sent Events endpoint `GET /stream?topic=dashboard|workers|logs`.
- **Fallback mode**: when SSE fails, frontend keeps periodic polling through React Query `refetchInterval`.

## Implemented screens

- Global Dashboard
- Migration Jobs
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

`/dashboard`, `/jobs`, `/jobs/action`, `/graph`, `/heatmap`, `/mapping`, `/workers`, `/logs`, `/conflicts`, `/integrity`, `/diff`, `/replay-preview`, `/system-health`, `/stream`.

All list endpoints use server-side `limit` and `offset` contract and accept filtering query params (`jobId`, `status`, `severity`, etc.).

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

## Feature flags and access boundaries

Current implementation keeps placeholders for role/feature integration in UI API contracts and `jobs/action` bridge. Existing runtime CLI scenarios remain untouched.
