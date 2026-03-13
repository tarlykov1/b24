# Migration Audit & Report

> ⚠️ Статус: документ описывает целевую/частично реализованную архитектуру foundation-этапа; часть пунктов остаётся planned/scaffold.


## Как работает аудит

Модуль аудита расположен в каталоге `/audit` и состоит из 5 частей:

- `integrity_check.js` — проверка целостности и ссылок после миграции.
- `portal_diff.js` — бережное сравнение старого и нового порталов (batch + delay).
- `error_registry.js` — единый реестр проблем.
- `report_generator.js` — экспорт отчётов (JSON/CSV/HTML).
- `snapshot.js` — фиксация состояния и сравнение повторных запусков.

Оркестрация выполняется через `audit/index.js` (`MigrationAuditModule`):

1. запускается `Data Integrity Check`;
2. выполняется `Portal Diff Check`;
3. создаётся snapshot;
4. строится отчёт и экспорты;
5. при финализации сохраняется итоговый snapshot.

## Проверки целостности

### Пользователи
- количество;
- совпадение `email`;
- флаг `active`;
- наличие ID в целевом портале.

### Задачи
- количество;
- поля `responsible_id`, `created_by`, `group_id`;
- сохранность комментариев по задаче.

### Группы/проекты
- владелец (`owner_id`);
- участники (`member_ids`).

### Комментарии
- количество;
- связь с существующей задачей.

### Проверка связей
- `task.responsible_id` существует;
- `task.created_by` существует;
- `task.group_id` существует;
- `comment.task_id` существует.

Если связь не найдена, система:
1. создаёт ошибку;
2. пишет в Error Registry;
3. добавляет `suggested_fix`.

## Бережная нагрузка

`Portal Diff Check` выполняет чтение страницами:

- `batch_size = 50` (настраивается);
- `delay = 300ms` между батчами (настраивается).

Это ограничивает интенсивность обращений к старому порталу и подходит для больших объёмов.

## Как читать отчёт

Отчёт включает:

- `migration_info`: дата, длительность, агрегаты;
- разделы `users/tasks/comments`;
- результаты `integrity`;
- `portal_diff` (включая текстовый блок);
- `errors` (реестр проблем);
- `snapshot`.

Поддерживаются форматы экспорта:

- JSON — полный технический отчёт;
- CSV — компактные метрики;
- HTML — человекочитаемый отчёт для передачи команде.

## Интерпретация ошибок и варианты исправления

Типы ошибок:

- `missing_entity` — сущность не мигрировала;
- `field_mismatch` — поле отличается от источника;
- `missing_relation` — нарушена ссылка между сущностями.

Рекомендации:

- `assign system user` — назначить системного пользователя для битых ответственных;
- `re-run ... migration` — точечно перезапустить перенос сущности;
- `sync ... using ID mapping table` — обновить связи через таблицу маппинга;
- `attach task to fallback migration project` — перепривязать задачу к резервному проекту.

## Повторные проверки (Run Audit)

Кнопка `Run Audit` в UI:

- повторно запускает сравнение порталов;
- показывает разницу с предыдущим snapshot;
- отображает новые данные после прошлого запуска.

## Примеры

Пример записи Error Registry:

```text
type: missing_relation
entity: task
entity_id: 8912
problem: responsible user not found
suggested_fix: assign system user
timestamp: 2026-03-13T10:21:12.219Z
```

Пример итогового текстового diff-отчёта:

```text
Users
Old portal: 352
New portal: 351
Difference: 1 missing

Tasks
Old portal: 12840
New portal: 12840
OK

Comments
Old portal: 55211
New portal: 55198
Difference: 13 missing
```
