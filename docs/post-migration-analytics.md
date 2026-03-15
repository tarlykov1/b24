# Post-Migration Analytics

## Integrity Scanner
`PostMigrationIntegrityScanner` проверяет:
- source vs target counts
- relation integrity
- cross-entity references
- user bindings
- timeline consistency
- file checksum match
- permission inheritance

## Adoption Analytics
`AdoptionAnalyticsEngine` строит Adoption Score из:
- login rate
- active users / departments
- CRM usage delta
- task activity delta
- file/automation usage

Также выделяет аномалии: неактивные департаменты, брошенные модули, падение активности.

## UX Telemetry
`UXTelemetryCollector` агрегирует UI errors, failed API calls, slow pages и permission/missing entity issues.
