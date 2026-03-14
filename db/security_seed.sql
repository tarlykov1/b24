INSERT INTO tenants (id, name) VALUES
('tenant-alpha', 'Alpha Holdings'),
('tenant-bravo', 'Bravo Group');

INSERT INTO workspaces (id, tenant_id, name, environment) VALUES
('ws-core', 'tenant-alpha', 'Core Migration', 'staging'),
('ws-prod', 'tenant-alpha', 'Production Migration', 'production'),
('ws-green', 'tenant-bravo', 'Greenfield Migration', 'production');

INSERT INTO operators (id, tenant_id, email, team_name) VALUES
('operator-1', 'tenant-alpha', 'operator1@alpha.example', 'migration-team-a'),
('approver-1', 'tenant-alpha', 'approver1@alpha.example', 'security-board'),
('auditor-1', 'tenant-alpha', 'auditor@alpha.example', 'audit-office'),
('superadmin-1', 'tenant-alpha', 'platform@ops.example', 'platform');
