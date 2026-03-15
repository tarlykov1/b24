# Simulation Risk Model

Система считает отдельные метрики:

- DurationRiskScore
- SourceImpactRiskScore
- IntegrityRiskScore
- ConflictRiskScore
- OperationalComplexityScore
- RecoveryDifficultyScore
- CutoverWindowRiskScore
- OverallSimulationRiskScore

## Факторы

- вероятность попадания в maintenance window;
- pressure source FS/read;
- conflict rate;
- custom field complexity.

Формула прозрачная и находится в `DefaultRiskEstimator`.
