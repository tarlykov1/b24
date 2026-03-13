# Bitrix24 Migration Toolkit (foundation / blueprint)

Репозиторий представляет собой **архитектурный foundation** для миграции Bitrix24 Enterprise (on-premise -> on-premise), а не готовый production-продукт.

## Текущее состояние (честно)

Реализовано на уровне каркаса и базовой доменной логики:
- структура модулей `export-agent` и `migration-module`;
- CLI-обвязка для основных стадий (часть команд — заглушки);
- SQL-схема состояния миграции;
- in-memory/persistence каркасы для mapping, queue, checkpoints, reconciliation, self-healing;
- админский UI как демонстрационный scaffold;
- unit/integration-тесты blueprint-уровня.

Не реализовано полностью (planned/scaffold):
- реальные адаптеры Bitrix REST/DB/filesystem;
- production-оркестрация воркеров и распределённая очередь;
- полноценная локализация UI через `locales/*.json`;
- end-to-end интеграции с реальными порталами.

## Структура

- `apps/export-agent` — source-side экспорт (преимущественно scaffold).
- `apps/migration-module` — target-side модуль миграции, orchestration и отчётность.
- `db/migration_schema.sql` — единая SQL-схема foundation.
- `docs/` — архитектурные и операционные документы (часть — blueprint/план).
- `tests/` — тесты согласованности текущего каркаса.
- `migration.config.yml` — runtime-конфиг (throttle/retry/batch/chunk).

## Схема БД (актуально)

См. `db/migration_schema.sql`.

Ключевые таблицы:
- `migration_job` — жизненный цикл задачи миграции;
- `migration_entity_map` — общее сопоставление source->target ID;
- `migration_user_map` — специализированный маппинг пользователей (статус, стратегия, примечание оператора);
- `migration_queue` — очередь операций и retry-состояние;
- `migration_checkpoint` — checkpoints по scope/stage;
- `migration_diff` — diff и manual review;
- `migration_logs` — единая таблица событий/операций миграции (**`migration_log` не используется**);
- `migration_integrity_issues` — результаты integrity-check;
- `migration_state` — high-water/state для resume/incremental.

## Конфигурация и профили

Файл `migration.config.yml` задаёт:
- `profile`: `safe|balanced|aggressive`;
- `rate_limit.source_rpm|target_rpm|heavy_rpm`;
- `batch_size`, `chunk_size`;
- `retry_policy.max_retries|base_delay_ms|max_delay_ms`;
- `parallel_workers`, `queue_max_size`, `delta_sync_interval`.

`ProductionConfigService` использует встроенные fallback-профили и накладывает значения из файла.

## CLI (текущее покрытие)

`bin/migration-module`:
- `migration start|pause|resume|status|verify|dry-run|delta-sync|cutover|rollback|report`.

`bin/export-agent`:
- минимальная CLI-заглушка; полноценные команды (`preflight/audit/batch/delta`) описаны как следующий этап.

## UI/admin

`apps/migration-module/ui/admin/index.php` — **демо-панель администратора**:
- dry-run, план, auto-mapping, прогресс, delta preview, verification, reconciliation, assistant;
- данные и действия частично моковые/демонстрационные;
- предназначено для валидации UX-потока blueprint, не для production-эксплуатации.

## Локализация

- Каталоги переводов: `locales/en.json`, `locales/ru.json`.
- Backend-коды переводятся `BackendMessageTranslator`.
- UI пока не подключён к JSON-каталогу полностью (planned).

## Тесты и проверки

- `tests/Unit/*` — доменные/архитектурные контракты foundation-уровня.
- `tests/Integration/*` — сценарные проверки dry-run, delta, cutover/rollback, устойчивости.

Запуск:
- `composer test` (после установки dev-зависимостей);
- `php -l <file>` для быстрой синтаксической проверки.

## Архитектурные цели (planned)

Репозиторий подготовлен как база для:
- export-agent;
- migration-module;
- pause/resume;
- repeatable reruns и delta sync;
- reconciliation/verification;
- self-healing;
- auto-mapping;
- scalable migration;
- audit/recovery/reports.

Часть пунктов уже имеет рабочий каркас, часть остаётся roadmap-задачей.
