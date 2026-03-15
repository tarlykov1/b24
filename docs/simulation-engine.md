# Migration Simulation Engine

Simulation Engine — доменный модуль для предиктивного моделирования миграции без записи в target.

## Что реализовано

- Модуль `apps/migration-module/src/Simulation` с разделением на `Domain`, `Application`, `Estimator`, `Scenario`, `Infrastructure`, `Report`, `UI/DTO`.
- Доменные сущности:
  - `SimulationScenario`
  - `SimulationInput`
  - `SimulationModel`
  - `SimulationRun`
  - `SimulationComparison`
- Конвейер симуляции: `SimulationEngine`.
- Реалистичные (коэффициентные) оценщики:
  - `UsersEstimator`
  - `CRMEstimator`
  - `TasksEstimator`
  - `FilesEstimator`
- Поддержка dependency penalties, retry amplification, resource pressure, window fit, risk scoring.
- Recommendation skeleton (`RecommendationEngine`).
- Failure simulation (`FailureSimulationService`).
- Report builder в JSON + Markdown (`SimulationReportBuilder`).
- Storage schema для scenario/run/comparison/calibration.
- CLI команды:
  - `simulate`
  - `simulate:compare`
  - `simulate:scenario`
  - `simulate:capacity`
  - `simulate:failures`
  - `simulate:report`

## Ограничения текущего foundation

- Коэффициенты базовые и прозрачные, но требуют калибровки по фактическим запускам.
- Часть сценариев покрыта preset-стратегиями + custom scenario.
- UI интеграция пока через DTO и CLI JSON output.
