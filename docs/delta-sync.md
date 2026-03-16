# Delta Sync

- `delta:plan`: detect changed/new entities after initial migration and enqueue changes.
- `delta:execute`: apply pending delta queue operations.

This mechanism is designed for stabilization and controlled reruns, not full bidirectional replication.
