# Reconciliation Engine и Certification Report

> ⚠️ Статус: документ описывает целевую/частично реализованную архитектуру foundation-этапа; часть пунктов остаётся planned/scaffold.


## Архитектура

`ReconciliationEngineService` запускается после:
1. основной миграции,
2. incremental sync,
3. self-healing repair cycle.

Движок работает батчами и с rate limit, поддерживает adaptive sampling для больших объёмов.

## Уровни сверки

1. **Количественная сверка**: source/target counts, difference, статус `OK|WARNING|CRITICAL`.
2. **ID и mapping**: missing target, lost mapping, duplicate mapping, target without source.
3. **Ключевые поля**: semantic compare с нормализацией дат, строк и сумм.
4. **Связи**: CRM relation integrity (deal-contact, deal-company, task-CRM, activity-CRM).
5. **Стадии**: source stage → stage mapping → target stage.
6. **Файлы**: наличие, размер, checksum, MIME.
7. **Комментарии/активности/задачи**: count, timestamps, author mapping, entity references.

## Adaptive sampling

- `< 50k` → `100%`
- `50k–500k` → `20%`
- `500k–1M` → `10%`
- `>1M` → `5%`

Sampling распределяется по времени изменений и по stage группам.

## Аномалии и repair cycle

Движок ищет:
- массовые потери данных,
- broken relations,
- неожиданные `null`,
- слишком короткие строки,
- аномальные расхождения сумм.

При нахождении проблем автоматически формируется цикл:

`reconciliation → repair → reconciliation`

Ошибки превращаются в repair jobs и передаются в self-healing pipeline.

## Certification Score

Метрики:
- Data completeness
- Relation integrity
- Field accuracy
- File integrity
- Overall score

Если overall score >= 98%, статус: **Migration Certified**.

## Certification Report

Генерируются форматы:
- `reports/certification_report.json`
- `reports/certification_report.html`
- `reports/certification_report.pdf`

Состав:
1. Summary (дата, версии, source/target, объём)
2. Статистика миграции
3. Результаты сверки по сущностям
4. Ошибки (unresolved/quarantine/manual review)
5. Integrity blocks (relations/stages/fields/files)
6. Certification score
7. Recommendations

## Migration Verification UI

В админке добавлен блок **Migration Verification**:
- ✔ Verified entities
- ⚠ Warnings
- ✖ Critical issues
- графики coverage / integrity / error distribution

## Постоянный мониторинг

Рекомендуется запускать reconciliation периодически после cutover:
1. запуск reconciliation,
2. если есть расхождения — incremental sync,
3. повторный reconciliation,
4. обновление certification report.
