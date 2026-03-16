CREATE TABLE IF NOT EXISTS jobs (
  id TEXT PRIMARY KEY,
  mode TEXT NOT NULL,
  status TEXT NOT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS queue (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  job_id TEXT NOT NULL,
  entity_type TEXT NOT NULL,
  source_id TEXT NOT NULL,
  payload TEXT NOT NULL,
  status TEXT NOT NULL,
  attempt INTEGER DEFAULT 0,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS entity_map (
  job_id TEXT NOT NULL,
  entity_type TEXT NOT NULL,
  source_id TEXT NOT NULL,
  target_id TEXT NOT NULL,
  checksum TEXT NOT NULL,
  status TEXT NOT NULL,
  conflict_marker INTEGER DEFAULT 0,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(job_id, entity_type, source_id)
);

CREATE TABLE IF NOT EXISTS user_map (
  job_id TEXT NOT NULL,
  source_id TEXT NOT NULL,
  target_id TEXT,
  strategy TEXT NOT NULL,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(job_id, source_id)
);

CREATE TABLE IF NOT EXISTS logs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  job_id TEXT NOT NULL,
  level TEXT NOT NULL,
  message TEXT NOT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS checkpoint (
  job_id TEXT NOT NULL,
  entity_type TEXT NOT NULL,
  last_source_id TEXT NOT NULL,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(job_id, entity_type)
);

CREATE TABLE IF NOT EXISTS diff (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  job_id TEXT NOT NULL,
  entity_type TEXT NOT NULL,
  source_id TEXT NOT NULL,
  category TEXT NOT NULL,
  detail TEXT NOT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS integrity_issues (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  job_id TEXT NOT NULL,
  entity_type TEXT NOT NULL,
  source_id TEXT NOT NULL,
  issue TEXT NOT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS state (
  entity_type TEXT PRIMARY KEY,
  last_sync_time TEXT,
  records_processed INTEGER DEFAULT 0,
  status TEXT,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS simulation_scenarios (
  id TEXT PRIMARY KEY,
  name TEXT NOT NULL,
  migration_mode TEXT NOT NULL,
  parameters_json TEXT NOT NULL,
  based_on_audit_id TEXT NOT NULL,
  policy_version TEXT NOT NULL,
  input_snapshot_json TEXT NOT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS simulation_runs (
  id TEXT PRIMARY KEY,
  scenario_id TEXT NOT NULL,
  result_json TEXT NOT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS simulation_comparisons (
  id TEXT PRIMARY KEY,
  name TEXT NOT NULL,
  result_json TEXT NOT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS estimator_calibration_profiles (
  id TEXT PRIMARY KEY,
  profile_name TEXT NOT NULL,
  coefficients_json TEXT NOT NULL,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE IF NOT EXISTS distributed_control_plane (
  job_id TEXT PRIMARY KEY,
  state_json TEXT NOT NULL,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS delta_cursors (
  job_id TEXT NOT NULL,
  entity_type TEXT NOT NULL,
  last_sync_timestamp TEXT,
  last_entity_id TEXT,
  watermark TEXT,
  phase TEXT NOT NULL DEFAULT 'incremental',
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(job_id, entity_type)
);

CREATE TABLE IF NOT EXISTS delta_entity_state (
  job_id TEXT NOT NULL,
  entity_type TEXT NOT NULL,
  entity_id TEXT NOT NULL,
  fingerprint TEXT NOT NULL,
  owner_key TEXT,
  updated_at TEXT,
  deleted INTEGER NOT NULL DEFAULT 0,
  last_seen_at TEXT DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(job_id, entity_type, entity_id)
);

CREATE TABLE IF NOT EXISTS delta_changes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  job_id TEXT NOT NULL,
  scan_id TEXT NOT NULL,
  phase TEXT NOT NULL,
  entity_type TEXT NOT NULL,
  entity_id TEXT NOT NULL,
  action TEXT NOT NULL,
  fingerprint TEXT,
  payload TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'pending',
  applied_at TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(job_id, scan_id, entity_type, entity_id, action)
);

CREATE TABLE IF NOT EXISTS delta_queue (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  job_id TEXT NOT NULL,
  entity_type TEXT NOT NULL,
  entity_id TEXT NOT NULL,
  change_type TEXT NOT NULL,
  payload TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'pending',
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS reconciliation_results (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  job_id TEXT NOT NULL,
  entity TEXT NOT NULL,
  entity_id TEXT NOT NULL,
  status TEXT NOT NULL,
  diff_type TEXT NOT NULL,
  diff_details TEXT,
  severity TEXT NOT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS cutover_reports (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  job_id TEXT NOT NULL,
  status TEXT NOT NULL,
  report_json TEXT NOT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS migration_conflicts (
  id TEXT PRIMARY KEY,
  job_id TEXT NOT NULL,
  entity_type TEXT NOT NULL,
  entity_id TEXT NOT NULL,
  conflict_type TEXT NOT NULL,
  payload TEXT NOT NULL,
  resolution_status TEXT NOT NULL DEFAULT 'open',
  resolution_policy TEXT,
  resolution_payload TEXT,
  resolved_at TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS migration_operator_decisions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  job_id TEXT NOT NULL,
  decision_key TEXT NOT NULL,
  policy TEXT NOT NULL,
  payload TEXT NOT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS migration_repair_plans (
  plan_id TEXT PRIMARY KEY,
  job_id TEXT NOT NULL,
  status TEXT NOT NULL,
  plan_json TEXT NOT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  applied_at TEXT
);

CREATE TABLE IF NOT EXISTS schema_snapshots (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  job_id TEXT NOT NULL,
  schema_version TEXT NOT NULL,
  runtime_mode TEXT NOT NULL,
  snapshot_json TEXT NOT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS entity_graph (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  job_id TEXT NOT NULL,
  graph_json TEXT NOT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS extract_progress (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  job_id TEXT NOT NULL,
  entity_type TEXT NOT NULL,
  table_name TEXT NOT NULL,
  strategy TEXT NOT NULL,
  batch_size INTEGER NOT NULL,
  rows_read INTEGER NOT NULL,
  boundaries_json TEXT NOT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS cursors (
  job_id TEXT NOT NULL,
  entity_type TEXT NOT NULL,
  table_name TEXT NOT NULL,
  strategy TEXT NOT NULL,
  last_processed_id TEXT,
  last_processed_timestamp TEXT,
  batch_start TEXT,
  batch_end TEXT,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(job_id, entity_type, table_name)
);

CREATE TABLE IF NOT EXISTS db_verify_results (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  job_id TEXT NOT NULL,
  verify_mode TEXT NOT NULL,
  result_json TEXT NOT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS migration_jobs (
  job_id TEXT PRIMARY KEY,
  plan_id TEXT,
  mode TEXT NOT NULL,
  status TEXT NOT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS migration_plans (
  plan_id TEXT PRIMARY KEY,
  job_id TEXT NOT NULL,
  plan_hash TEXT NOT NULL,
  config_hash TEXT NOT NULL,
  plan_json TEXT NOT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS execution_batches (
  batch_id TEXT PRIMARY KEY,
  job_id TEXT NOT NULL,
  plan_id TEXT NOT NULL,
  phase TEXT NOT NULL,
  entity_type TEXT NOT NULL,
  stable_order INTEGER NOT NULL,
  status TEXT NOT NULL,
  attempts INTEGER NOT NULL DEFAULT 0,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS execution_steps (
  step_id TEXT PRIMARY KEY,
  job_id TEXT NOT NULL,
  plan_id TEXT NOT NULL,
  phase TEXT NOT NULL,
  batch_id TEXT,
  entity_type TEXT NOT NULL,
  source_id TEXT NOT NULL,
  reserved_target_id TEXT,
  actual_target_id TEXT,
  operation_type TEXT NOT NULL,
  payload_hash TEXT,
  status TEXT NOT NULL,
  attempt_count INTEGER NOT NULL DEFAULT 0,
  verification_status TEXT,
  error_class TEXT,
  error_code TEXT,
  diagnostic_blob TEXT,
  started_at TEXT,
  finished_at TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS id_reservations (
  plan_id TEXT NOT NULL,
  entity_type TEXT NOT NULL,
  source_id TEXT NOT NULL,
  requested_target_id TEXT NOT NULL,
  reserved_target_id TEXT NOT NULL,
  policy TEXT NOT NULL,
  reason TEXT NOT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(plan_id, entity_type, source_id)
);

CREATE TABLE IF NOT EXISTS relation_map (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  plan_id TEXT NOT NULL,
  relation_key TEXT NOT NULL,
  owner_entity_type TEXT NOT NULL,
  owner_source_id TEXT NOT NULL,
  target_entity_type TEXT NOT NULL,
  target_source_id TEXT NOT NULL,
  target_resolved_id TEXT,
  status TEXT NOT NULL,
  reason TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(plan_id, relation_key)
);

CREATE TABLE IF NOT EXISTS file_transfer_map (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  plan_id TEXT NOT NULL,
  source_file_id TEXT,
  source_path TEXT NOT NULL,
  source_checksum TEXT,
  source_size INTEGER,
  target_file_id TEXT,
  target_path TEXT,
  target_checksum TEXT,
  target_size INTEGER,
  relation_key TEXT,
  status TEXT NOT NULL,
  resume_token TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS verification_results (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  job_id TEXT NOT NULL,
  plan_id TEXT NOT NULL,
  phase TEXT NOT NULL,
  entity_type TEXT,
  source_id TEXT,
  level INTEGER NOT NULL,
  status TEXT NOT NULL,
  details_json TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS failure_events (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  job_id TEXT NOT NULL,
  plan_id TEXT,
  phase TEXT,
  batch_id TEXT,
  entity_type TEXT,
  source_id TEXT,
  classification TEXT NOT NULL,
  error_code TEXT,
  diagnostic_blob TEXT,
  retryable INTEGER NOT NULL DEFAULT 0,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS checkpoint_state (
  job_id TEXT NOT NULL,
  plan_id TEXT NOT NULL,
  phase TEXT NOT NULL,
  cursor TEXT,
  payload_json TEXT,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(job_id, plan_id, phase)
);

CREATE TABLE IF NOT EXISTS replay_guard (
  idempotency_key TEXT PRIMARY KEY,
  job_id TEXT NOT NULL,
  plan_id TEXT NOT NULL,
  phase TEXT NOT NULL,
  entity_type TEXT NOT NULL,
  source_id TEXT NOT NULL,
  payload_hash TEXT NOT NULL,
  status TEXT NOT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS run_locks (
  lock_key TEXT PRIMARY KEY,
  job_id TEXT NOT NULL,
  plan_id TEXT,
  owner TEXT NOT NULL,
  status TEXT NOT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS source_snapshots (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  job_id TEXT NOT NULL,
  plan_id TEXT,
  snapshot_type TEXT NOT NULL,
  snapshot_hash TEXT NOT NULL,
  snapshot_json TEXT NOT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS job_metrics (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  job_id TEXT NOT NULL,
  plan_id TEXT,
  metric_key TEXT NOT NULL,
  metric_value REAL NOT NULL,
  tags_json TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
