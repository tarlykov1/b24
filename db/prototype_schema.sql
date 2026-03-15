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
