# Simulation Scenarios

`ScenarioBuilder` создаёт встроенные пресеты:

- SafeSource
- Balanced
- FastCutover
- LowNightImpact
- WeekendFull
- IncrementalCatchUp
- HighIntegrity
- FilesLast
- CRMFirst

Для оператора доступен custom режим через `simulate:scenario <workers> <window_hours>`.

## Важные параметры

- `worker_count`
- `verify_depth`
- `file_strategy`
- `strictness`
- `window_hours`
- `throttle_qps`
- `batch_size`
- `max_retry`
- `conflict_rate`
