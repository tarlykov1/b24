-- Enterprise security/governance plane for migration operator platform.

CREATE TABLE IF NOT EXISTS tenants (
  id VARCHAR(64) PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS workspaces (
  id VARCHAR(64) PRIMARY KEY,
  tenant_id VARCHAR(64) NOT NULL,
  name VARCHAR(255) NOT NULL,
  environment VARCHAR(32) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_workspaces_tenant (tenant_id)
);

CREATE TABLE IF NOT EXISTS operators (
  id VARCHAR(64) PRIMARY KEY,
  tenant_id VARCHAR(64) NOT NULL,
  email VARCHAR(255) NOT NULL,
  team_name VARCHAR(128) NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_operators_tenant (tenant_id)
);

CREATE TABLE IF NOT EXISTS roles (
  id VARCHAR(64) PRIMARY KEY,
  name VARCHAR(64) NOT NULL UNIQUE,
  is_platform_role TINYINT(1) NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS permissions (
  id VARCHAR(128) PRIMARY KEY,
  domain_name VARCHAR(64) NOT NULL,
  description TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id VARCHAR(64) NOT NULL,
  permission_id VARCHAR(128) NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  KEY idx_role_permissions_permission (permission_id)
);

CREATE TABLE IF NOT EXISTS grants (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  operator_id VARCHAR(64) NOT NULL,
  permission_id VARCHAR(128) NOT NULL,
  scope_type VARCHAR(32) NOT NULL,
  scope_id VARCHAR(64) NOT NULL,
  grant_type VARCHAR(16) NOT NULL,
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_grants_operator_scope (operator_id, scope_type, scope_id),
  KEY idx_grants_expiry (expires_at)
);

CREATE TABLE IF NOT EXISTS policies (
  id VARCHAR(64) PRIMARY KEY,
  tenant_id VARCHAR(64) NULL,
  workspace_id VARCHAR(64) NULL,
  policy_json JSON NOT NULL,
  version INT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS approval_requests (
  id VARCHAR(64) PRIMARY KEY,
  tenant_id VARCHAR(64) NOT NULL,
  workspace_id VARCHAR(64) NOT NULL,
  action_type VARCHAR(128) NOT NULL,
  payload_hash CHAR(64) NOT NULL,
  risk_level VARCHAR(16) NOT NULL,
  reason TEXT NOT NULL,
  requested_by VARCHAR(64) NOT NULL,
  quorum_required INT NOT NULL DEFAULT 1,
  status VARCHAR(32) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_approval_scope_status (tenant_id, workspace_id, status),
  KEY idx_approval_action_risk (action_type, risk_level)
);

CREATE TABLE IF NOT EXISTS approval_decisions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  approval_request_id VARCHAR(64) NOT NULL,
  actor_id VARCHAR(64) NOT NULL,
  decision VARCHAR(16) NOT NULL,
  comment TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_approval_decisions_request (approval_request_id)
);

CREATE TABLE IF NOT EXISTS audit_events (
  id VARCHAR(64) PRIMARY KEY,
  tenant_id VARCHAR(64) NOT NULL,
  workspace_id VARCHAR(64) NOT NULL,
  actor_id VARCHAR(64) NOT NULL,
  actor_roles_json JSON NOT NULL,
  action_type VARCHAR(128) NOT NULL,
  target_resource_type VARCHAR(64) NOT NULL,
  target_resource_id VARCHAR(128) NULL,
  payload_snapshot_json JSON NULL,
  policy_decision_json JSON NOT NULL,
  approval_reference VARCHAR(64) NULL,
  result_status VARCHAR(32) NOT NULL,
  correlation_id VARCHAR(128) NOT NULL,
  trace_id VARCHAR(128) NOT NULL,
  risk_score INT NOT NULL,
  security_labels_json JSON NOT NULL,
  prev_hash CHAR(64) NULL,
  hash CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_actor_time (actor_id, created_at),
  KEY idx_audit_resource_time (target_resource_type, target_resource_id, created_at),
  KEY idx_audit_action_risk (action_type, risk_score),
  KEY idx_audit_tenant_workspace (tenant_id, workspace_id)
);

CREATE TABLE IF NOT EXISTS sessions (
  id VARCHAR(64) PRIMARY KEY,
  operator_id VARCHAR(64) NOT NULL,
  tenant_id VARCHAR(64) NOT NULL,
  user_agent VARCHAR(512) NULL,
  ip_address VARCHAR(64) NULL,
  status VARCHAR(32) NOT NULL,
  last_seen_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sessions_operator (operator_id, status)
);

CREATE TABLE IF NOT EXISTS break_glass_sessions (
  id VARCHAR(64) PRIMARY KEY,
  operator_id VARCHAR(64) NOT NULL,
  tenant_id VARCHAR(64) NOT NULL,
  reason TEXT NOT NULL,
  status VARCHAR(32) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_break_glass_status_expiry (status, expires_at)
);

CREATE TABLE IF NOT EXISTS resource_locks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(64) NOT NULL,
  workspace_id VARCHAR(64) NOT NULL,
  resource_type VARCHAR(64) NOT NULL,
  resource_id VARCHAR(128) NOT NULL,
  owner_operator_id VARCHAR(64) NOT NULL,
  lock_status VARCHAR(16) NOT NULL,
  lock_version INT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_resource_lock (tenant_id, workspace_id, resource_type, resource_id),
  KEY idx_resource_locks_owner (owner_operator_id, lock_status)
);

CREATE TABLE IF NOT EXISTS security_incidents (
  id VARCHAR(64) PRIMARY KEY,
  tenant_id VARCHAR(64) NOT NULL,
  workspace_id VARCHAR(64) NOT NULL,
  title VARCHAR(255) NOT NULL,
  severity VARCHAR(16) NOT NULL,
  status VARCHAR(32) NOT NULL,
  timeline_json JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
