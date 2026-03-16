# MySQL-assisted migration architecture

## Runtime modes
- `rest-only`: source/target чтение через REST adapters.
- `mysql-assisted`: source truth читается из MySQL (read-only), apply в target только через ApplyAdapter.
- `hybrid`: REST + MySQL + filesystem telemetry в едином runtime metadata.

## Backbone components
- `MySqlSourceDiscovery`: introspection таблиц/колонок/индексов, эвристики доменов Bitrix24, custom fields, schema snapshot.
- `EntityGraphBuilder`: строит dependency graph, dependency edges, grouping и topological order.
- `MySqlSourceExtractor`: батчевый extract с throttling, cursor/high-watermark, пауза/резюмирование.
- `CursorManager`: deterministic cursor state по `(job_id, entity, table)`.
- `DbVerificationEngine`: source/target/mixed verify foundation (counts, mapping integrity, relations, orphan detection).

## Runtime storage extensions
Добавлены таблицы:
- `schema_snapshots`
- `entity_graph`
- `extract_progress`
- `cursors`
- `db_verify_results`

Все записи связаны с `job_id`.

## Safety guardrails
- source DB только read-only session.
- запрет unsafe full scan без `WHERE` + `LIMIT`.
- configurable batch limits / throttling / timeout.
- никаких direct writes в target DB.

## CLI
Новые команды:
- `migration db:discover <job_id>`
- `migration db:schema-snapshot <job_id>`
- `migration db:entity-graph <job_id>`
- `migration db:extract <job_id> <entity> <table> [strategy] [batch_size]`
- `migration db:cursors <job_id>`
- `migration verify:db <job_id> [runtime|source_db|target_db|mixed]`

Все команды возвращают JSON и требуют `job_id`.
