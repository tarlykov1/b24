# Operator Guide: финальная верификация и безопасный дозапуск

## Режимы запуска
- **dry-run**: только чтение, mapping, проверки конфликтов и построение плана без записи в target.
- **initial_import**: первый полный проход.
- **incremental_sync / delta_sync**: повторные запуски, переносятся только новые/измененные сущности.
- **verification / reconciliation**: послемиграционная сверка качества переноса.

## Dry-run
1. В UI откройте блок `Dry-run` и нажмите `Run dry-run`.
2. В CLI используйте `MigrationCommands::dryRun(...)`.
3. Проверьте итог: create/update/skip/conflict/manual_review.

## План миграции
- План содержит entity type, source id, target id, действие, причину и зависимости.
- В UI фильтруется по статусам действий.
- Для выгрузки используйте `migration_summary.json` и `migration_summary.csv`.

## Боевая миграция
1. Выполните dry-run и проверьте конфликты.
2. Утвердите стратегии конфликтов.
3. Запустите миграцию и отслеживайте прогресс по этапам и сущностям.

## Дозапуск (delta sync)
- Перед запуском смотрите preview: найдено `new`, `changed`, `conflicts`.
- Если конфликтов нет — безопасно продолжайте.
- Если есть конфликты — примените решения и повторите preview.

## Отчеты
Обязательный набор:
1. migration_summary.json
2. migration_summary.csv
3. conflicts.json
4. unresolved_links.json
5. skipped_entities.json
6. delta_sync_report.json
7. verification_report.json
8. performance_report.json

## Интерпретация verification report
Успешной считается миграция, когда:
- по ключевым группам `matched` близок к `total_source`;
- `missing_in_target` и `conflicts` в допустимом операционном пороге;
- unresolved links разобраны и закрыты операторами.
