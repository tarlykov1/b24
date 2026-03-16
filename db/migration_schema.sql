-- Migration toolkit schema (MySQL/MariaDB compatible)

CREATE TABLE IF NOT EXISTS migration_job (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    mode VARCHAR(32) NOT NULL,
    status VARCHAR(32) NOT NULL,
    config_json JSON NOT NULL,
    preview_required TINYINT(1) NOT NULL DEFAULT 0,
    source_checkpoint VARCHAR(255) NULL,
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_job_status (status),
    KEY idx_job_mode (mode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS migration_entity_map (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    entity_type VARCHAR(64) NOT NULL,
    source_id VARCHAR(128) NOT NULL,
    target_id VARCHAR(128) NOT NULL,
    migrated_flag TINYINT(1) NOT NULL DEFAULT 0,
    preserved_source_id TINYINT(1) NOT NULL DEFAULT 0,
    remap_reason VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_entity_map (job_id, entity_type, source_id),
    KEY idx_entity_target (entity_type, target_id),
    CONSTRAINT fk_entity_map_job FOREIGN KEY (job_id) REFERENCES migration_job(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS migration_user_map (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    source_user_id VARCHAR(128) NOT NULL,
    target_user_id VARCHAR(128) NULL,
    mapping_strategy VARCHAR(64) NOT NULL DEFAULT 'exact_or_remap',
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    note VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_map_source (job_id, source_user_id),
    KEY idx_user_map_target (job_id, target_user_id),
    KEY idx_user_map_status (job_id, status),
    CONSTRAINT fk_user_map_job FOREIGN KEY (job_id) REFERENCES migration_job(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS migration_queue (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    stage VARCHAR(64) NOT NULL,
    batch_no INT NOT NULL DEFAULT 0,
    entity_type VARCHAR(64) NOT NULL,
    operation VARCHAR(64) NOT NULL,
    dedupe_key VARCHAR(191) NOT NULL,
    payload_json JSON NOT NULL,
    status VARCHAR(32) NOT NULL,
    attempt_count INT NOT NULL DEFAULT 0,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    locked_at DATETIME NULL,
    locked_by VARCHAR(128) NULL,
    last_error TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_queue_dedupe (job_id, dedupe_key),
    KEY idx_queue_status_available (job_id, status, available_at),
    CONSTRAINT fk_queue_job FOREIGN KEY (job_id) REFERENCES migration_job(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS migration_checkpoint (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    scope VARCHAR(128) NOT NULL,
    stage VARCHAR(64) NULL,
    batch_no INT NULL,
    checkpoint_value VARCHAR(255) NOT NULL,
    checkpoint_meta_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_checkpoint_scope (job_id, scope),
    CONSTRAINT fk_checkpoint_job FOREIGN KEY (job_id) REFERENCES migration_job(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS migration_diff (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    entity_type VARCHAR(64) NOT NULL,
    source_id VARCHAR(128) NOT NULL,
    target_id VARCHAR(128) NULL,
    category VARCHAR(64) NOT NULL,
    diff_json JSON NULL,
    requires_manual_review TINYINT(1) NOT NULL DEFAULT 0,
    resolution_status VARCHAR(32) NOT NULL DEFAULT 'open',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_diff_job_category (job_id, category),
    KEY idx_diff_entity (entity_type, source_id),
    CONSTRAINT fk_diff_job FOREIGN KEY (job_id) REFERENCES migration_job(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS migration_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    timestamp DATETIME NOT NULL,
    operation VARCHAR(128) NOT NULL,
    entity_type VARCHAR(64) NOT NULL,
    entity_id VARCHAR(128) NULL,
    status VARCHAR(16) NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_migration_logs_status_date (status, timestamp),
    KEY idx_migration_logs_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS migration_integrity_issues (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type VARCHAR(64) NOT NULL,
    entity_id VARCHAR(128) NOT NULL,
    problem_type VARCHAR(64) NOT NULL,
    description TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_integrity_entity (entity_type, entity_id),
    KEY idx_integrity_problem (problem_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS migration_state (
    entity_type VARCHAR(64) NOT NULL,
    last_processed_id VARCHAR(128) NOT NULL,
    last_sync_time DATETIME NOT NULL,
    records_processed BIGINT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('pending', 'running', 'paused', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (entity_type),
    KEY idx_migration_state_status (status, last_sync_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS migration_snapshots (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    snapshot_id VARCHAR(64) NOT NULL,
    snapshot_started_at DATETIME NOT NULL,
    source_cutoff_time DATETIME NOT NULL,
    snapshot_status VARCHAR(32) NOT NULL,
    per_module_cursor_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_snapshot_job (job_id, snapshot_id),
    CONSTRAINT fk_snapshot_job FOREIGN KEY (job_id) REFERENCES migration_job(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS migration_snapshot_watermarks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    snapshot_id VARCHAR(64) NOT NULL,
    entity_type VARCHAR(64) NOT NULL,
    last_extracted_source_marker_json JSON NULL,
    last_reconciled_source_marker_json JSON NULL,
    last_verified_source_marker_json JSON NULL,
    last_target_sync_marker_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_snapshot_entity_watermark (snapshot_id, entity_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS migration_delta_runs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    snapshot_id VARCHAR(64) NOT NULL,
    status VARCHAR(32) NOT NULL,
    started_at DATETIME NOT NULL,
    finished_at DATETIME NULL,
    stats_json JSON NULL,
    PRIMARY KEY (id),
    KEY idx_delta_runs_job (job_id),
    CONSTRAINT fk_delta_runs_job FOREIGN KEY (job_id) REFERENCES migration_job(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS migration_delta_changes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    delta_run_id BIGINT UNSIGNED NOT NULL,
    entity_type VARCHAR(64) NOT NULL,
    source_id VARCHAR(128) NOT NULL,
    change_type VARCHAR(32) NOT NULL,
    source_marker VARCHAR(191) NULL,
    payload_json JSON NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'planned',
    PRIMARY KEY (id),
    KEY idx_delta_changes_entity (entity_type, source_id),
    CONSTRAINT fk_delta_changes_run FOREIGN KEY (delta_run_id) REFERENCES migration_delta_runs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS migration_entity_states (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    entity_type VARCHAR(64) NOT NULL,
    source_id VARCHAR(128) NOT NULL,
    state VARCHAR(64) NOT NULL,
    reason VARCHAR(255) NULL,
    dependency_type VARCHAR(64) NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_entity_state (job_id, entity_type, source_id),
    KEY idx_entity_state_state (state),
    CONSTRAINT fk_entity_state_job FOREIGN KEY (job_id) REFERENCES migration_job(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS migration_reconciliation_queue (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    entity_type VARCHAR(64) NOT NULL,
    source_id VARCHAR(128) NOT NULL,
    reason VARCHAR(128) NOT NULL,
    dependency_type VARCHAR(64) NULL,
    retry_count INT NOT NULL DEFAULT 0,
    last_attempt DATETIME NULL,
    next_scheduled_attempt DATETIME NULL,
    escalation_state VARCHAR(64) NOT NULL DEFAULT 'pending',
    payload_json JSON NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'queued',
    PRIMARY KEY (id),
    KEY idx_reconcile_schedule (job_id, status, next_scheduled_attempt),
    CONSTRAINT fk_reconcile_job FOREIGN KEY (job_id) REFERENCES migration_job(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS migration_conflicts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    conflict_id VARCHAR(64) NOT NULL,
    entity_type VARCHAR(64) NOT NULL,
    source_id VARCHAR(128) NULL,
    target_id VARCHAR(128) NULL,
    conflict_type VARCHAR(128) NOT NULL,
    severity VARCHAR(32) NOT NULL,
    payload_json JSON NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'open',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_conflict (job_id, conflict_id),
    KEY idx_conflict_status (job_id, status),
    CONSTRAINT fk_conflict_job FOREIGN KEY (job_id) REFERENCES migration_job(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS migration_target_change_markers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    entity_type VARCHAR(64) NOT NULL,
    target_id VARCHAR(128) NOT NULL,
    marker_source VARCHAR(64) NOT NULL,
    marker_json JSON NOT NULL,
    changed_by_migration TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_target_change (job_id, entity_type, target_id),
    CONSTRAINT fk_target_change_job FOREIGN KEY (job_id) REFERENCES migration_job(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hypercare_window (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    window_name VARCHAR(64) NOT NULL,
    start_at DATETIME NOT NULL,
    end_at DATETIME NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_hypercare_window_job (job_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hypercare_event (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(64) NOT NULL,
    severity VARCHAR(32) NOT NULL,
    payload_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_hypercare_event_job (job_id, severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS integrity_scan_result (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    integrity_score DECIMAL(6,4) NOT NULL,
    summary_json JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_integrity_scan_job (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reconciliation_task (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    issue_type VARCHAR(64) NOT NULL,
    strategy VARCHAR(64) NOT NULL,
    status VARCHAR(32) NOT NULL,
    payload_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_reconciliation_task_job (job_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS late_write_event (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    entity_type VARCHAR(64) NOT NULL,
    entity_id VARCHAR(128) NOT NULL,
    window_name VARCHAR(64) NOT NULL,
    status VARCHAR(32) NOT NULL,
    payload_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_late_write_job (job_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS adoption_metric (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    metric_name VARCHAR(64) NOT NULL,
    metric_value DECIMAL(12,4) NOT NULL,
    measured_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_adoption_metric_job (job_id, metric_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS business_flow_check (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    flow_name VARCHAR(128) NOT NULL,
    status VARCHAR(32) NOT NULL,
    details_json JSON NULL,
    checked_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_business_flow_job (job_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS performance_metric (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    metric_name VARCHAR(64) NOT NULL,
    pre_value DECIMAL(12,4) NOT NULL,
    post_value DECIMAL(12,4) NOT NULL,
    regression_ratio DECIMAL(8,4) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_performance_metric_job (job_id, metric_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS optimization_recommendation (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    area VARCHAR(64) NOT NULL,
    recommendation TEXT NOT NULL,
    impact_score INT NOT NULL,
    risk_level VARCHAR(32) NOT NULL,
    implementation_effort VARCHAR(16) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_optimization_recommendation_job (job_id, area)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ux_telemetry_event (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(64) NOT NULL,
    feature VARCHAR(128) NOT NULL,
    duration_ms INT NOT NULL DEFAULT 0,
    payload_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ux_telemetry_job (job_id, event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS migration_success_score (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    score DECIMAL(6,4) NOT NULL,
    result_bucket VARCHAR(32) NOT NULL,
    components_json JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_success_score_job (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS migration_final_report (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    report_json JSON NOT NULL,
    json_path VARCHAR(255) NULL,
    html_path VARCHAR(255) NULL,
    pdf_path VARCHAR(255) NULL,
    docx_path VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_final_report_job (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS migration_archive (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    archive_path VARCHAR(255) NOT NULL,
    archive_manifest_json JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_migration_archive_job (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hypercare_issues (
    issue_id VARCHAR(64) NOT NULL,
    entity_type VARCHAR(64) NOT NULL,
    entity_id VARCHAR(128) NOT NULL,
    severity VARCHAR(16) NOT NULL,
    description TEXT NOT NULL,
    source_reference JSON NULL,
    target_reference JSON NULL,
    detected_at DATETIME NOT NULL,
    repair_status VARCHAR(32) NOT NULL DEFAULT 'pending',
    PRIMARY KEY (issue_id),
    KEY idx_hypercare_issue_entity (entity_type, severity),
    KEY idx_hypercare_issue_repair (repair_status, detected_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS adoption_metrics (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    metric_name VARCHAR(64) NOT NULL,
    metric_value DECIMAL(12,4) NOT NULL,
    measured_at DATETIME NOT NULL,
    payload_json JSON NULL,
    PRIMARY KEY (id),
    KEY idx_adoption_metrics_name (metric_name, measured_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS anomalies (
    anomaly_id VARCHAR(64) NOT NULL,
    anomaly_type VARCHAR(64) NOT NULL,
    severity ENUM('info', 'warning', 'critical') NOT NULL,
    description TEXT NOT NULL,
    detected_at DATETIME NOT NULL,
    payload_json JSON NULL,
    PRIMARY KEY (anomaly_id),
    KEY idx_anomalies_type (anomaly_type, severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS performance_metrics (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    metric_name VARCHAR(64) NOT NULL,
    metric_value DECIMAL(12,4) NOT NULL,
    threshold_value DECIMAL(12,4) NULL,
    severity VARCHAR(16) NOT NULL DEFAULT 'info',
    measured_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_performance_metrics_name (metric_name, measured_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS repair_actions (
    action_id VARCHAR(64) NOT NULL,
    issue_id VARCHAR(64) NOT NULL,
    action_type VARCHAR(128) NOT NULL,
    status VARCHAR(32) NOT NULL,
    dry_run TINYINT(1) NOT NULL DEFAULT 1,
    executed_at DATETIME NOT NULL,
    PRIMARY KEY (action_id),
    KEY idx_repair_actions_issue (issue_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS adoption_risk_reports (
    report_id VARCHAR(64) NOT NULL,
    department VARCHAR(128) NOT NULL,
    summary TEXT NOT NULL,
    severity VARCHAR(16) NOT NULL,
    generated_at DATETIME NOT NULL,
    payload_json JSON NULL,
    PRIMARY KEY (report_id),
    KEY idx_adoption_risk_department (department, severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS optimization_recommendations (
    recommendation_id VARCHAR(64) NOT NULL,
    domain VARCHAR(64) NOT NULL,
    description TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    payload_json JSON NULL,
    PRIMARY KEY (recommendation_id),
    KEY idx_optimization_recommendations_domain (domain, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hypercare_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    log_type VARCHAR(64) NOT NULL,
    severity VARCHAR(16) NOT NULL DEFAULT 'info',
    message TEXT NOT NULL,
    payload_json JSON NULL,
    logged_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_hypercare_logs_type (log_type, logged_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
