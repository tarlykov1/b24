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
    <h2>Job Control</h2>
    <button>Start</button>
    <button>Pause</button>
    <button>Resume</button>
    <button>Soft Stop</button>
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
