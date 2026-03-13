# Operator Guide (foundation stage)

Документ описывает операционные сценарии, которые уже представлены в текущем каркасе. Это не production runbook «под ключ».

## Поддерживаемые режимы
- `dry_run`
- `initial_load`
- `incremental_sync`
- `delta_sync`
- `reconciliation`
- `verification`

## Dry-run
1. В UI: блок **Dry-run** -> `Run dry-run`.
2. В CLI: `bin/migration-module migration dry-run <jobId>`.
3. Проверьте итоговые счётчики: `create/update/skip/conflict/manual_review`.

## План и конфликты
- План формируется `MigrationPlanningService`.
- Конфликты требуют решения оператора (`ConflictResolutionService`).
- На текущем этапе часть решений хранится в каркасном repository-слое.

## Delta sync / повторный прогон
- Запуск preview: `bin/migration-module migration delta-sync <jobId>`.
- Сценарий использует checkpoint/high-water подход (foundation-реализация).
- При конфликтах нужен ручной разбор перед продолжением.

## Проверка и отчёты
- Верификация/сверка представлены сервисами reconciliation/reporting.
- `bin/migration-module migration report <jobId>` формирует набор JSON/CSV отчётов каркасного уровня.

## Runtime-конфиг (актуальные ключи)
Файл `migration.config.yml`:
- `profile` (`safe|balanced|aggressive`)
- `rate_limit.source_rpm|target_rpm|heavy_rpm`
- `batch_size`, `chunk_size`
- `retry_policy.max_retries|base_delay_ms|max_delay_ms`
- `parallel_workers`, `queue_max_size`, `delta_sync_interval`

## Что пока остаётся scaffold
- полноценная оркестрация distributed workers;
- production-grade очереди и внешние lock-механизмы;
- end-to-end интеграции с реальными API Bitrix24.
