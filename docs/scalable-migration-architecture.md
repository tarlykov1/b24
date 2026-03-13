# Масштабируемая миграция Bitrix24: миллионы сущностей

## Что реализовано в коде

### 1) Пакетная и потоковая архитектура
- Добавлен `MigrationStagePlanner` с dependency-aware DAG этапами:
  1. users/directories/pipelines/stages/custom_fields
  2. leads/contacts/companies/deals/smart_processes
  3. tasks/relations
  4. comments/activities/files
  5. incremental_sync/repeat_verification
- Данные дробятся на `chunk_size` и `batch_size`.
- Для каждого типа сущности формируется независимая очередь с флагом `parallel_safe`.
- Добавлен `ScalableMigrationOrchestrator`: поэтапный прогон очередей, контроль курсоров чанка/батча и запись checkpoint.

### 2) Управление нагрузкой
- Добавлен `AdaptiveRateLimiter`:
  - раздельные лимиты для `source`, `target`, `heavy`;
  - профили `safe`, `balanced`, `aggressive`;
  - adaptive throttling на 429/5xx/timeout-сигналах;
  - плавное восстановление RPM после успешных запросов.
- Обновлён `ThrottlingService` на профильные имена (`safe/balanced/aggressive`).

### 3) Checkpoint/restart/resume
- В `MigrationRepository` добавлены:
  - хранение состояния очередей (`saveQueueState/queueState`),
  - расширенные checkpoints,
  - high-water mark,
  - API для метрик и выборки checkpoint по scope.
- `ScalableMigrationOrchestrator` пишет checkpoint на каждый батч и состояние очередей для resume после падения.

### 4) Идемпотентность и identity mapping
- Расширен mapping-слой:
  - `saveIdentityMapping/findIdentityMapping` в `MigrationRepository`;
  - в `IdMappingService` добавлено сохранение метода матчинга, версии миграции, сигнатуры и времени sync.
- Поддержаны методы сопоставления: `exact_id`, `id_remap`, произвольные (`external_key`, `heuristic_match`, `manual_override`) через `mapWithMetadata`.

### 5) Incremental sync и high-water mark
- Добавлен `HighWaterMarkSyncService`:
  - хранит watermark по типу сущности,
  - строит delta по `updated_at/created_at`,
  - fallback на hash-сигнатуру для изменения payload,
  - пишет sync-checkpoint + migration-checkpoint.

### 6) Память и I/O
- Оркестратор не держит весь pipeline в памяти:
  - обработка чанками/батчами,
  - ограничение размера внутренних единиц работы,
  - чекпоинты и состояние очередей сохраняются в persistent repository.

### 7) Файлы
- В DAG файлы вынесены в отдельную тяжёлую фазу (`stage_4_heavy_dependencies`) после карточек.
- Отдельный rate-limit канал `heavy` для безопасного копирования файлов.
- Рекомендация: запускать файловую фазу отдельно при высоком риске нагрузки (см. запуск ниже).

### 8) Bulk/batching
- На текущем этапе реализован безопасный client-side batching + queue grouping.
- В местах с API bulk — расширять executor до пакетных вызовов, сохраняя тот же checkpoint contract.

### 9) UI/UX
- `AdminController::auditConfig()` расширен:
  - профили нагрузки,
  - режимы `dry_run`, `initial_full_migration`, `incremental_sync`, `repeat_verification`.
- `MonitoringDashboardService` расширен метриками throughput/lag/rate-limit/load/checkpoint/success ratio/reconciliation coverage.

### 10) Метрики и наблюдаемость
- В dashboard возвращаются ключевые KPI:
  - throughput per entity,
  - latency,
  - retries, rate-limit hits,
  - queue lag/backlog,
  - memory/worker usage,
  - file transfer stats,
  - success ratio,
  - reconciliation coverage.

### 11) Тестирование на больших объёмах
- Добавлены unit-тесты:
  - DAG-планировщик,
  - adaptive limiter,
  - high-water mark delta,
  - checkpoint+queue state для resume.

## Узкие места, которые устранены
- Монолитный прогон «одним циклом» заменён на поэтапный DAG + очереди.
- Нет разделения лимитов source/target/heavy -> теперь есть.
- Нет устойчивого resume очередей -> добавлено сохранение queue state.
- Нет high-water mark на уровне entity type -> добавлен.
- Mapping без явной версии/метода сопоставления -> добавлен identity mapping слой.

## Поведение на очень больших объёмах
- На 100k/1M/5M сущностей процесс остаётся предсказуемым за счёт fixed-size чанков/батчей.
- При деградации API (429/5xx) скорость автоматически снижается, что защищает старый портал.
- При повторном запуске:
  - `initial full` идёт без тяжёлой глобальной сверки на пустой целевой системе,
  - `incremental` и `repeat verification` используют watermark/checkpoint и не плодят дубликаты.

## Как запускать

### Initial full migration
1. Выбрать профиль (`safe` для production first run).
2. Запустить основной цикл с `initialRun=true`.
3. Отдельно запустить file phase при необходимости.

### Incremental sync
1. Запустить с `initialRun=false`.
2. `HighWaterMarkSyncService` дозальёт новые/изменённые записи.
3. Проверить checkpoint и метрики queue lag/throughput.

### Repeat verification
1. Запустить режим `repeat_verification` после incremental.
2. Сверить `success_ratio` и `reconciliation_coverage` в dashboard.

## Ограничения и безопасные fallback
- Если API сущности не поддерживает фильтр по `updated_at`, используется hash-сигнатура payload (дороже, но безопасно).
- Полноценный server-side bulk зависит от конкретного Bitrix endpoint; при отсутствии — остаётся client batching.
- Для экстремальных файловых объёмов рекомендуется выделенный run только файлов с `safe` профилем.
