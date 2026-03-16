# MySQL Schema Overview (platform)

Main schema file: `db/mysql_platform_schema.sql`.

Includes:
- migration metadata (`schema_migrations`, `migration_lock`)
- lifecycle (`jobs`, `job_steps`, `queue`, `checkpoints`)
- mappings (`entity_map`, `user_map`)
- observability (`logs`, `integrity_issues`, `diff_state`)
- installer state (`wizard_install_state`, `install_audit_events`, `control_plane_settings`)
- resilience (`retry_state`, `delta_sync_cursors`, `cutover_plan_state`, `hypercare_metrics`)
