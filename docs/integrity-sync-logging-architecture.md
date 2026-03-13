# Integrity, Sync и Logging Architecture (foundation)

## Поток обработки
1. План/очередь формируются в migration-module.
2. Проверки целостности формируют записи в `migration_integrity_issues`.
3. Состояние incremental/resume фиксируется в `migration_state` и checkpoints.
4. Операционные события логируются в `migration_logs`.

## Единая договорённость по логам
В проекте используется **одна** основная SQL-таблица логов: `migration_logs`.

- `PdoMigrationLogRepository` читает/пишет в `migration_logs`.
- Таблица `migration_log` удалена из схемы как дублирующая.

## Ключевые таблицы, связанные с integrity/sync
- `migration_logs`
- `migration_integrity_issues`
- `migration_state`
- `migration_checkpoint`

## Текущий статус
- Архитектурные контуры согласованы.
- Часть проверок и отчётности реализована как scaffold/контракт.
- Полная production-реализация требует подключения реальных адаптеров и execution runtime.
