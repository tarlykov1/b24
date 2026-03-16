# Hypercare Operations

## Hypercare Monitoring Window
После успешного go-live платформа включает post-migration Hypercare mode с настраиваемой длительностью:
- 7 days
- 14 days
- 30 days

По завершению выполняется переход в состояние `migration_completed`, при этом вся hypercare-история остается доступной.

## Continuous Monitoring Scope
Hypercare engine мониторит целевую Bitrix24 среду по направлениям:
- Data integrity: users, departments, contacts, companies, deals, tasks, comments, activities, smart processes, files, disk objects.
- Adoption analytics: DAU/WAU, login frequency, task/CRM/file activity, feature usage, department activity.
- Anomaly detection: usage/data/permission anomalies.
- Performance telemetry: REST latency, DB query latency, file download latency, worker queue delay, API rate-limit utilization.
- Integrity repair: dry-run и execute режимы для автоматического восстановления связей/файлов/прав.
- Optimization: CRM/tasks/disk recommendations.

## Scan Frequency and Safety
Режим hypercare безопасен для production и использует low-impact execution:
- Integrity scan — каждые 6 часов.
- Adoption analytics — ежедневно.
- Performance monitoring — near realtime.

Safety controls:
- Adaptive throttling.
- Limit DB queries per cycle.
- Limit REST calls per cycle.
- Incremental scheduling to avoid load spikes.

## Storage Tables
Результаты post-migration intelligence сохраняются в:
- `hypercare_issues`
- `adoption_metrics`
- `anomalies`
- `performance_metrics`
- `repair_actions`
- `adoption_risk_reports`
- `optimization_recommendations`
- `hypercare_logs`

## CLI
Legacy commands:
- `migration:hypercare:start`
- `migration:hypercare:scan`
- `migration:hypercare:reconcile`
- `migration:hypercare:report`
- `migration:hypercare:archive`

Post-migration operations suite:
- `hypercare:start [7|14|30]`
- `hypercare:status [started_at] [duration_days]`
- `hypercare:scan`
- `hypercare:repair [--dry-run|--execute]`
- `hypercare:adoption`
- `hypercare:optimize`

Все команды возвращают JSON-ответы.

## API
- `GET /hypercare/status`
- `GET /hypercare/integrity-report`
- `GET /hypercare/adoption`
- `GET /hypercare/performance`
- `POST /hypercare/reconciliation/run`
- `POST /hypercare/integrity/scan`
- `GET /hypercare/final-report`

## Надежность и аудит
Hypercare-процессы должны быть restart-safe, traceable, versioned и audited.
