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

CREATE TABLE IF NOT EXISTS migration_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    level VARCHAR(16) NOT NULL,
    channel VARCHAR(32) NOT NULL,
    entity_type VARCHAR(64) NULL,
    old_id VARCHAR(128) NULL,
    new_id VARCHAR(128) NULL,
    action VARCHAR(64) NOT NULL,
    error_message TEXT NULL,
    retry_count INT NOT NULL DEFAULT 0,
    execution_time_ms INT NOT NULL DEFAULT 0,
    context_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_log_job_channel (job_id, channel),
    KEY idx_log_entity (entity_type, old_id),
    CONSTRAINT fk_log_job FOREIGN KEY (job_id) REFERENCES migration_job(id)
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
