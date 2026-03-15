# Scalable Migration Architecture (blueprint)

Документ фиксирует целевую и частично реализованную архитектуру масштабируемой миграции.

## Что уже есть в коде (foundation)
- `MigrationStagePlanner` — каркас dependency-aware этапов.
- `ScalableMigrationOrchestrator` — каркас chunk/batch прогона и checkpoint-resume.
- `AdaptiveRateLimiter` — профильные лимиты `safe|balanced|aggressive`.
- `HighWaterMarkSyncService` — база для incremental/delta подхода.
- Monitoring/metrics сервисы — scaffold для наблюдаемости.

## Конфигурационные параметры
Фактические параметры в `migration.config.yml`:
- `rate_limit.source_rpm|target_rpm|heavy_rpm`
- `batch_size`, `chunk_size`
- `retry_policy.max_retries|base_delay_ms|max_delay_ms`
- `parallel_workers`, `queue_max_size`, `delta_sync_interval`

## Практический смысл
- `chunk_size` ограничивает размер обрабатываемого окна данных.
- `batch_size` ограничивает размер единицы записи/операции.
- `retry_policy` и профильные rate-limits защищают source/target от перегрузки.

## Ограничения текущего этапа
- Нет полноценного distributed execution runtime.
- Нет production-ready реализации для всех Bitrix сущностей.
- Часть heavy-entity сценариев описана как roadmap.

## Следующий этап
- подключить реальные адаптеры Bitrix API;
- вынести queue/state в надёжный внешний storage;
- добавить нагрузочные integration-тесты и SLO/SLA контуры.


## Distributed Worker Control Plane (incremental extension)
- Добавлен `DistributedWorkerControlPlane` как совместимое расширение для управления распределёнными воркерами без переписывания прототипа.
- Control plane хранит состояние `status`, `assignments`, `queue_retries`, `paused_reason` в репозитории и поддерживает идемпотентные команды pause/resume/retry.
- Heartbeat воркеров обновляет lease/last_heartbeat и интегрируется с `AdaptiveRateLimiter` (успех → плавное восстановление, ошибки 429/5xx → backoff).

### CLI команды control plane
- `migration:workers:init <job_id> <queue_csv> <workers_csv>`
- `migration:workers:status <job_id>`
- `migration:workers:pause <job_id> [reason]`
- `migration:workers:resume <job_id>`
- `migration:workers:retry <job_id> <queue_name>`
- `migration:workers:heartbeat <job_id> <worker_id> <ok|fail> [status_code]`

Эти команды не затрагивают существующие `validate|dry-run|execute|resume|verify` и добавлены как backward-compatible слой для постепенного перехода к production distributed runtime.
