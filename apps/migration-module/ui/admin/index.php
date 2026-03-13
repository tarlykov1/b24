<?php

declare(strict_types=1);
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Bitrix24 Migration Admin</title>
    <style>
        body { font-family: sans-serif; margin: 2rem; background: #fafafa; }
        .panel { border: 1px solid #d9d9d9; padding: 1rem; margin-bottom: 1rem; border-radius: 8px; background: #fff; }
        .grid { display: grid; gap: 1rem; grid-template-columns: repeat(2, minmax(260px, 1fr)); }
        .kpis { display: grid; gap: .5rem; grid-template-columns: repeat(4, minmax(100px, 1fr)); }
        .kpi { background: #f4f8ff; border-radius: 6px; padding: .5rem; }
        .actions button { margin-right: .5rem; margin-bottom: .5rem; }
        .status-list { display: flex; flex-wrap: wrap; gap: .4rem; }
        .status-pill { border: 1px solid #c8c8c8; border-radius: 999px; padding: .2rem .6rem; font-size: 12px; }
        pre { background: #f8f8f8; border-radius: 6px; padding: 1rem; overflow-x: auto; max-height: 320px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #ececec; padding: .35rem; text-align: left; }
    </style>
</head>
<body>
<h1>Migration Control Panel</h1>

<div class="panel">
    <h2>Dry-run</h2>
    <p>Проходит чтение, mapping, проверки конфликтов и построение плана без записи в target.</p>
    <div class="actions">
        <button id="dry-run-btn">Run dry-run</button>
        <label><input id="incremental-flag" type="checkbox"> Incremental mode</label>
    </div>
    <div class="kpis" id="dry-run-kpis"></div>
</div>

<div class="panel">
    <h2>План миграции</h2>
    <label>Фильтр действия:
        <select id="plan-filter">
            <option value="">all</option><option>create</option><option>update</option><option>skip</option><option>conflict</option><option>manual_review</option>
        </select>
    </label>
    <pre id="migration-plan">[]</pre>
</div>

<div class="grid">
    <div class="panel">
        <h2>Ход миграции</h2>
        <div class="status-list">
            <?php foreach (['queued','reading','mapping','ready','migrating','paused','verifying','completed','completed_with_warnings','failed','cancelled'] as $status): ?>
                <span class="status-pill"><?= htmlspecialchars($status, ENT_QUOTES) ?></span>
            <?php endforeach; ?>
        </div>
        <table>
            <tr><th>Progress</th><th>Value</th></tr>
            <tr><td>By entities</td><td id="progress-entities">0%</td></tr>
            <tr><td>By stages</td><td id="progress-stages">0%</td></tr>
            <tr><td>Current object</td><td id="progress-current">-</td></tr>
            <tr><td>Errors / warnings</td><td id="progress-problems">0 / 0</td></tr>
        </table>
        <div class="actions"><button>Pause</button><button>Resume</button></div>
    </div>

    <div class="panel">
        <h2>Дозапуск / delta sync</h2>
        <p id="delta-preview">No preview yet</p>
        <div class="actions"><button id="delta-preview-btn">Preview delta</button><button>Continue</button><button>Cancel</button></div>
    </div>
</div>

<div class="grid">
    <div class="panel"><h2>Сверка после миграции</h2><pre id="reconciliation-report">{}</pre></div>
    <div class="panel"><h2>Конфликты</h2><pre id="conflicts-report">[]</pre></div>
</div>


<div class="panel">
    <h2>Migration Assistant</h2>
    <div class="grid">
        <div>
            <p><b>Overall readiness score:</b> <span id="assistant-readiness">-</span></p>
            <p><b>Recommended load profile:</b> <span id="assistant-load">-</span></p>
            <p><b>Next best action:</b> <span id="assistant-next">-</span></p>
        </div>
        <div>
            <h3>Why this recommendation</h3>
            <pre id="assistant-why">{}</pre>
        </div>
    </div>
    <h3>Operator checklist</h3>
    <pre id="assistant-checklist">[]</pre>
    <button id="assistant-run-btn">Run assistant analysis</button>
</div>

<div class="panel">
    <h2>Скачать отчеты</h2>
    <ul>
        <li>migration_summary.json / .csv</li>
        <li>conflicts.json</li>
        <li>unresolved_links.json</li>
        <li>skipped_entities.json</li>
        <li>delta_sync_report.json</li>
        <li>verification_report.json</li>
        <li>performance_report.json</li>
    </ul>
</div>

<script>
const source = {
    users: [{id: '1', email: 'owner@x.io'}],
    tasks: [{id: '10', responsible_id: '1', created_by: '1'}],
    crm_deals: [{id: '77', title: 'Deal', company_id: '15'}],
};
const target = { users: [{id: '1', email: 'owner@x.io'}], tasks: [], crm_deals: [] };

const computePlan = () => {
    const items = [];
    for (const [entity, rows] of Object.entries(source)) {
        const targetRows = new Map((target[entity] ?? []).map((r) => [r.id, r]));
        for (const row of rows) {
            const action = targetRows.has(row.id) ? 'update' : 'create';
            items.push({entity_type: entity, source_id: row.id, action, reason: action === 'create' ? 'not_found_in_target' : 'target_exists'});
        }
    }
    return items;
};

const renderPlan = (items) => {
    const filter = document.getElementById('plan-filter').value;
    const filtered = filter ? items.filter((i) => i.action === filter) : items;
    document.getElementById('migration-plan').textContent = JSON.stringify(filtered, null, 2);
};

document.getElementById('dry-run-btn').addEventListener('click', () => {
    const plan = computePlan();
    const summary = plan.reduce((acc, item) => ((acc[item.action] = (acc[item.action] ?? 0) + 1), acc), {create: 0, update: 0, skip: 0, conflict: 0, manual_review: 0});
    document.getElementById('dry-run-kpis').innerHTML = Object.entries(summary).map(([key, val]) => `<div class="kpi"><b>${key}</b><div>${val}</div></div>`).join('');
    renderPlan(plan);
    document.getElementById('progress-entities').textContent = '40%';
    document.getElementById('progress-stages').textContent = 'mapping';
    document.getElementById('progress-current').textContent = plan[0]?.entity_type + ':' + plan[0]?.source_id;
});

document.getElementById('plan-filter').addEventListener('change', () => renderPlan(computePlan()));

document.getElementById('delta-preview-btn').addEventListener('click', () => {
    document.getElementById('delta-preview').textContent = 'found new: 2, changed: 1, conflicts: 0';
    document.getElementById('reconciliation-report').textContent = JSON.stringify({users: {total_source: 1, total_target: 1, matched: 1}}, null, 2);
    document.getElementById('conflicts-report').textContent = JSON.stringify([], null, 2);
});


const runAssistant = () => {
    const snapshot = {
        source_available: true,
        target_available: true,
        custom_fields_count: 175,
        files_count: 72000,
        relation_density: 0.69,
        mapping_coverage: 0.86,
        stage_mapping_coverage: 0.88,
        unresolved_conflicts: 26,
    };

    const risk = (snapshot.files_count > 50000 ? 20 : 0)
        + (snapshot.mapping_coverage < 0.9 ? 25 : 0)
        + (snapshot.stage_mapping_coverage < 0.95 ? 20 : 0)
        + (snapshot.unresolved_conflicts > 20 ? 20 : 0);
    const readiness = Math.max(0, 100 - risk);
    const load = risk >= 70 ? 'safe' : risk >= 40 ? 'balanced' : 'aggressive';

    const checklist = [
        'Запустить dry-run с приоритетом на mapping conflicts',
        'Проверить stage mapping и ambiguous enum values',
        'Вынести файлы в отдельную фазу',
        'Подтвердить conservative healing policy для первого прохода'
    ];

    document.getElementById('assistant-readiness').textContent = readiness;
    document.getElementById('assistant-load').textContent = load;
    document.getElementById('assistant-next').textContent = 'Сначала dry-run, затем guided full migration';
    document.getElementById('assistant-checklist').textContent = JSON.stringify(checklist, null, 2);
    document.getElementById('assistant-why').textContent = JSON.stringify({
        input_factors: snapshot,
        rule: 'high files + low mapping coverage => isolate file phase + safe start',
        expected_effect: 'lower source pressure and fewer quarantine entities',
        risk_if_ignored: '429/timeout spikes and unstable reruns'
    }, null, 2);
};

document.getElementById('assistant-run-btn').addEventListener('click', runAssistant);

</script>
</body>
</html>
