# Набор инструментов миграции Bitrix24 Enterprise (каркас)

Производственный каркас отказоустойчивого и возобновляемого инструмента для миграции «живого» on-premise портала Bitrix24 Enterprise на новый on-premise портал Bitrix24 Enterprise.

## Объём текущего шага

Этот коммит содержит только архитектурный каркас:
- структуру директорий;
- namespaces и границы модулей;
- схему базы данных для состояния миграции;
- каркасы сервисов;
- каркасы CLI-команд;
- базовый каркас админского UI;
- список известных Bitrix-специфичных неопределённостей.

Полная логика миграции на этом этапе ещё не реализована.

## Архитектура

## Часть A: Export Agent (исходный портал)
Путь: `apps/export-agent`

Зоны ответственности:
- безопасный/троттлируемый пакетный экспорт;
- извлечение дельты с учётом checkpoint;
- файловые экспортные манифесты;
- безопасное для источника поведение (операции только на чтение).

Принципы проектирования:
- изоляция вызовов Bitrix в адаптерах;
- stateless-воркеры + сохранённые checkpoints;
- явные batch-контракты и идемпотентные export-чанки.

## Часть B: Migration Module (целевой портал)
Путь: `apps/migration-module`

Зоны ответственности:
- жизненный цикл очереди и оркестрация воркеров;
- идемпотентная стратегия upsert/mapping;
- diff-first поведение для повторных прогонов;
- управление паузой/возобновлением/мягкой остановкой;
- верификация и отчётность.

Принципы проектирования:
- восстановление связей по принципу mapping-first;
- стабильный state machine жизненного цикла задачи;
- импорт автоматизаций в выключенном состоянии по умолчанию;
- попытка сохранить source ID с безопасным fallback через remap при конфликтах.

## Режимы запуска
- `initial_load`
- `incremental_sync`
- `delta_sync`
- `reconciliation`
- `verification`

## Жизненный цикл задачи
- `draft`
- `ready`
- `running`
- `pausing`
- `paused`
- `resuming`
- `stopping`
- `stopped`
- `completed`
- `failed`
- `verification_required`
- `verified`

## Схема базы данных
См. `db/migration_schema.sql`, где описаны таблицы:
- `migration_entity_map`
- `migration_user_map`
- `migration_queue`
- `migration_job`
- `migration_log`
- `migration_checkpoint`
- `migration_diff`

## Каркас CLI-команд
### Export Agent
- `export:preflight`
- `export:audit`
- `export:batch`
- `export:delta`

### Migration Module
- `migration:preflight`
- `migration:audit`
- `migration:job:create`
- `migration:job:start`
- `migration:job:pause`
- `migration:job:resume`
- `migration:job:stop`
- `migration:diff`
- `migration:verify`

## Каркас админского UI
Путь: `apps/migration-module/ui/admin/index.php`

Предусмотрены плейсхолдеры для:
- статуса preflight;
- сводки аудита;
- действий управления задачей;
- gate согласования diff;
- статуса верификации.

## Локализация (i18n)
- Файлы каталога локализации находятся в `/locales/en.json` и `/locales/ru.json`.
- Бэкенд-сообщения (`MIGRATION_STARTED`, `MIGRATION_PAUSED`, `MIGRATION_COMPLETED` и т. п.) переводятся через `MigrationModule\Application\I18n\BackendMessageTranslator`.
- В текущем UI-каркасе админки тексты пока захардкожены на английском и не подключены к JSON-каталогу.

### Как добавить новый язык
1. Добавьте новый файл локали в `/locales`, например `/locales/de.json`.
2. Скопируйте все ключи из `en.json` и заполните переводы.
3. Расширьте словарь в `BackendMessageTranslator::DICTIONARY` для backend-кодов событий/ошибок.
4. Подключите новый каталог в UI-слое (после реализации клиентского переключателя языка).

## Стратегия безопасности и идемпотентности
- постановка в очередь неизменяемых единиц работы с детерминированными ключами дедупликации;
- продвижение checkpoint только после надёжной записи;
- ретраи с backoff и адаптивными сигналами троттлинга;
- отсутствие деструктивных операций на стороне источника;
- запись в target защищена mapping-слоем и обработкой конфликтов.

## Следующий шаг (реализация)
1. реализовать адаптеры для Bitrix REST/DB/filesystem;
2. реализовать preflight-проверки и генерацию отчётов;
3. реализовать сбор аудита сущностей;
4. реализовать воркеры очереди + цикл обратной связи с троттлером;
5. реализовать миграторы по сущностям с контролем mapping;
6. реализовать набор верификации и markdown/json-отчёты.

## Известные неопределённости
См. `docs/bitrix-uncertainties.md`.


## Этап 7: финальная верификация, dry-run и безопасный дозапуск

Реализованы ключевые блоки подготовки и контроля:
- `DryRunService` — полный dry-run без записи в target, с итоговой сводкой действий и ручного разбора.
- `MigrationPlanningService` — построение плана миграции по сущностям (create/update/skip/conflict/manual_review), причины и зависимости.
- `PostMigrationReconciliationService` — послемиграционная сверка totals, matched/mismatched, missing/extra/conflicts и отчёт unresolved links.
- `DeltaSyncService` — preview дозапуска по стратегии updated_at/modified_at + hash payload + mapping/checkpoint.
- расширенный `ConflictResolutionService` — единый набор стратегий и сохранение решений оператора.
- `FinalReportService` — генерация обязательного набора отчётов: summary JSON/CSV, conflicts, unresolved links, skipped, delta, verification, performance.
- UI-обновление админки: отдельные блоки для dry-run, плана, прогресса, reconciliation, конфликтов и скачивания отчётов.

Подробные инструкции оператора — `docs/operator-guide.md`.
