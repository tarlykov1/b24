# Integrity, Incremental Sync and Logging Architecture

## 1) Архитектура модуля проверки

### Поток выполнения этапа
1. `PreflightService` выполняет блокирующие проверки перед запуском миграции.
2. Данные этапа миграции обрабатываются через очередь (`QueueService`) и ограничитель (`ThrottlingService`).
3. API-вызовы к Bitrix выполняются через `ApiRequestExecutor` с retry-политикой 1/3s/5s/3.
4. После завершения этапа запускается `VerificationService`:
   - проверки целостности по типу сущности,
   - запись несоответствий в `migration_integrity_issues`,
   - предупреждения в централизованный лог.
5. Обновляется `migration_state` для возобновления и incremental sync.
6. `MigrationReportService` сохраняет итог в `reports/migration_report.json`.

### Ключевые модули
- `Application/Preflight/*` — предзапусковые проверки API, прав, лимитов и ресурсов.
- `Application/Sync/*` — режимы FULL/INCREMENTAL и retry API-запросов.
- `Application/Verification/VerificationService` — проверка целостности.
- `Application/Logging/MigrationLogger` + `Infrastructure/Persistence/Log/*` — централизованное логирование в файл и БД.
- `Infrastructure/Http/AdminController` + `ui/admin/index.php` — фильтрация логов по типу/дате/сущности.

## 2) Структура таблиц

Добавлены таблицы:
- `migration_logs`
  - `timestamp`, `operation`, `entity_type`, `entity_id`, `status`, `message`.
- `migration_integrity_issues`
  - `entity_type`, `entity_id`, `problem_type`, `description`, `created_at`.
- `migration_state`
  - `entity_type`, `last_processed_id`, `last_sync_time`, `records_processed`, `status`.

## 3) Основные функции синхронизации

- `IncrementalSyncService::selectRecordsToSync()`
  - FULL_MIGRATION: переносит все.
  - INCREMENTAL_SYNC: переносит только новые/измененные по `DATE_MODIFY` или `UPDATED_AT`.
- `IncrementalSyncService::markCompleted()`
  - фиксирует checkpoint в `migration_state`.
- `ApiRequestExecutor::executeWithRetry()`
  - 3 попытки, ожидания 3s/5s, при неуспехе лог ERROR.
- `QueueService::enqueue()` + `reserve()`
  - последовательная очередь c троттлингом запросов.

## 4) Система логирования

`MigrationLogger` пишет записи в несколько репозиториев одновременно:
- `FileMigrationLogRepository` -> `logs/migration.log` (JSON lines).
- `PdoMigrationLogRepository` -> `migration_logs`.

Поддерживаются уровни:
- `INFO`
- `WARNING`
- `ERROR`

Фильтрация логов:
- по типу (`status`),
- по сущности (`entity_type`),
- по периоду (`date_from`, `date_to`).

## 5) Пример отчета миграции

См. `reports/migration_report.json`.
