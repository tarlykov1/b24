<?php

declare(strict_types=1);

$validation = [
    'integrity' => 'Pending',
    'statistics' => 'Pending',
    'warnings' => 0,
    'errors' => 0,
    'problems' => [],
];
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Bitrix24 Migration Admin</title>
    <style>
        body { font-family: sans-serif; margin: 2rem; }
        .panel { border: 1px solid #ccc; padding: 1rem; margin-bottom: 1rem; border-radius: 8px; }
        .actions button { margin-right: .5rem; }
        .validation-grid { display: grid; grid-template-columns: repeat(2, minmax(200px, 1fr)); gap: 1rem; }
        .status-ok { color: #1b7f2a; }
        .status-warn { color: #946000; }
        .status-error { color: #b00020; }
        table { width: 100%; border-collapse: collapse; margin-top: .75rem; }
        th, td { border: 1px solid #ddd; padding: .4rem; text-align: left; }
    </style>
</head>
<body>
<h1>Migration Control Panel</h1>
<div class="panel"><h2>Preflight</h2><p>Status: Ready</p></div>
<div class="panel"><h2>Audit</h2><p>Inventory: collected</p></div>
<div class="panel actions">
    <h2 data-i18n="panel.control">Job Control</h2>
    <button data-i18n="migration.start">Start migration</button>
    <button data-i18n="migration.pause">Pause</button>
    <button data-i18n="migration.resume">Resume</button>
    <button data-i18n="migration.stop">Stop</button>
</div>
<div class="panel"><h2 data-i18n="panel.diff">Diff Approval Gate</h2><p><span data-i18n="migration.preview">Preview changes</span>: <span data-i18n="status.todo">TODO</span></p></div>
<div class="panel"><h2 data-i18n="panel.verification">Validation</h2><p data-i18n="migration.validation">Validation page</p></div>
<div class="panel">
    <h2 data-i18n="panel.dashboard">Migration Dashboard</h2>
    <div class="dashboard-grid">
        <div class="kpi"><div class="muted" data-i18n="dashboard.processed">Processed records</div><div id="processedCount">0</div></div>
        <div class="kpi"><div class="muted" data-i18n="dashboard.errors">Errors</div><div id="errorCount">0</div></div>
        <div class="kpi"><div class="muted" data-i18n="dashboard.lastSync">Last synchronization</div><div id="lastSync">-</div></div>
    </div>
</div>
<div class="panel">
    <h2 data-i18n="panel.logs">Logs</h2>
    <p><strong data-i18n="migration.progress">Progress</strong>: <span id="progressValue">0%</span></p>
    <ul>
        <li data-message-key="migration.message.MIGRATION_STARTED">MIGRATION_STARTED</li>
        <li data-message-key="migration.message.MIGRATION_PAUSED">MIGRATION_PAUSED</li>
        <li data-message-key="migration.message.MIGRATION_COMPLETED">MIGRATION_COMPLETED</li>
        <li data-i18n="warnings.sample">Warning: rate limit is close</li>
        <li data-i18n="errors.sample">Error: connection to Bitrix24 API failed</li>
    </ul>
</div>
<div class="panel"><h2>Diff Approval Gate</h2><p>Continue sync / Cancel</p></div>
<div class="panel">
    <h2>Migration validation</h2>
    <div class="validation-grid">
        <div><strong>Integrity check result:</strong> <span class="status-ok"><?= htmlspecialchars($validation['integrity']) ?></span></div>
        <div><strong>Statistics comparison:</strong> <?= htmlspecialchars($validation['statistics']) ?></div>
        <div><strong>Warnings:</strong> <span class="status-warn"><?= (int) $validation['warnings'] ?></span></div>
        <div><strong>Errors:</strong> <span class="status-error"><?= (int) $validation['errors'] ?></span></div>
    </div>
    <p class="actions"><button>Run validation</button></p>
    <h3>List of problems</h3>
    <table>
        <thead><tr><th>Entity</th><th>Old ID</th><th>New ID</th><th>Issue</th></tr></thead>
        <tbody>
        <?php if ($validation['problems'] === []) : ?>
            <tr><td colspan="4">No problems detected.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
