# Simulation Calibration

Калибровка выполняется локально, без внешних AI/API:

- `EstimatorCalibrationService` — корректировка коэффициентов по отношению actual/predicted.
- `SimulationVsActualDiffReport` — отчёт по отклонениям прогноза от факта.

## Поток

1. Сохранить фактические метрики dry-run/execute/verify.
2. Построить diff-report.
3. Обновить коэффициенты baseline.
4. Повторно запустить simulation для похожего контура.
