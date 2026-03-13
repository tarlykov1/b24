import { ErrorRegistry } from './error_registry.js';
import { runDataIntegrityCheck } from './integrity_check.js';
import { runPortalDiffCheck } from './portal_diff.js';
import { generateMigrationReport, reportToCSV, reportToHTML, reportToJSON } from './report_generator.js';
import { buildSnapshot, diffSnapshots } from './snapshot.js';
import { RecoveryEngine } from '../recovery/recovery_engine.js';

export class MigrationAuditModule {
  constructor({
    batch_size = 50,
    delay_ms = 300,
    logger = console,
    recovery = {},
  } = {}) {
    this.batch_size = batch_size;
    this.delay_ms = delay_ms;
    this.logger = logger;
    this.errorRegistry = new ErrorRegistry(logger);
    this.lastSnapshot = null;
    this.lastRunAt = null;
    this.recoveryEngine = new RecoveryEngine({
      errorRegistry: this.errorRegistry,
      logger,
      batch_size: recovery.batch_size ?? 20,
      delay_ms: recovery.delay_ms ?? 500,
      retry_limit: recovery.retry_limit ?? 3,
      auto_recovery: recovery.auto_recovery ?? false,
      system_user_id: recovery.system_user_id ?? '0',
      inactive_user_policy: recovery.inactive_user_policy ?? 'create_deactivated_user',
    });
  }

  async runAudit({ sourcePortalData, targetPortalData, fetchOld, fetchNew, migrationMeta }) {
    this.errorRegistry = new ErrorRegistry(this.logger);
    this.recoveryEngine.errorRegistry = this.errorRegistry;
    const startedAt = Date.now();

    const integrity = runDataIntegrityCheck({ source: sourcePortalData, target: targetPortalData, errorRegistry: this.errorRegistry });
    const portalDiff = await runPortalDiffCheck({ fetchOld, fetchNew, batch_size: this.batch_size, delay_ms: this.delay_ms });
    const snapshot = buildSnapshot(targetPortalData);
    const rerunDiff = diffSnapshots(this.lastSnapshot, snapshot);

    let recoveryResult = null;
    if (this.recoveryEngine.auto_recovery && this.errorRegistry.all().length > 0) {
      recoveryResult = await this.recoveryEngine.run({ sourcePortal: sourcePortalData, targetPortal: targetPortalData });
    }

    const report = generateMigrationReport({
      migration: {
        migration_date: migrationMeta?.migration_date ?? new Date().toISOString(),
        duration_ms: Date.now() - startedAt,
        users: migrationMeta?.users ?? { total_migrated: snapshot.users_count, skipped: 0, deactivated: 0 },
        tasks: migrationMeta?.tasks ?? { migrated: snapshot.tasks_count, updated: 0, errors: 0 },
        comments: migrationMeta?.comments ?? { migrated: snapshot.comments_count, skipped: 0 },
      },
      integrity,
      portalDiff,
      errors: this.errorRegistry.all(),
      snapshot,
      stats: {
        users: snapshot.users_count,
        tasks: snapshot.tasks_count,
        comments: snapshot.comments_count,
        groups: snapshot.groups_count,
      },
    });

    this.lastSnapshot = snapshot;
    this.lastRunAt = new Date().toISOString();

    return {
      status: integrity.status,
      report,
      exports: {
        json: reportToJSON(report),
        csv: reportToCSV(report),
        html: reportToHTML(report),
      },
      rerun_diff: rerunDiff,
      issues: this.errorRegistry.all(),
      recovery: recoveryResult,
      run_at: this.lastRunAt,
    };
  }

  async runRecovery({ sourcePortalData, targetPortalData, last_checkpoint = null } = {}) {
    return this.recoveryEngine.run({
      sourcePortal: sourcePortalData,
      targetPortal: targetPortalData,
      last_checkpoint,
    });
  }

  async retryFailedRecovery({ sourcePortalData, targetPortalData, last_checkpoint = null } = {}) {
    return this.recoveryEngine.retryFailed({
      sourcePortal: sourcePortalData,
      targetPortal: targetPortalData,
      last_checkpoint,
    });
  }

  ignoreError(errorId, reason = 'ignored_by_operator') {
    return this.errorRegistry.setRecoveryStatus(errorId, 'skipped', { reason });
  }

  finalizeMigration(targetPortalData) {
    const snapshot = buildSnapshot(targetPortalData);
    this.lastSnapshot = snapshot;

    return {
      finalized_at: new Date().toISOString(),
      snapshot,
      action_items: this.errorRegistry.all().map((entry) => ({
        entity: entry.entity,
        entity_id: entry.entity_id,
        suggested_fix: entry.suggested_fix,
      })),
      recovery_history: this.recoveryEngine.history,
    };
  }
}
