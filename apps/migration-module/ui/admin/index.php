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
        .ok { color: #217a00; }
        .warning { color: #a76a00; }
        .critical { color: #b30000; }
        pre { background: #f8f8f8; border-radius: 6px; padding: 1rem; overflow-x: auto; max-height: 320px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #ececec; padding: .35rem; text-align: left; }
        .chart { display: grid; gap: .4rem; margin-top: .5rem; }
        .bar { height: 14px; border-radius: 8px; background: #e9eef8; overflow: hidden; }
        .bar > span { display: block; height: 100%; background: linear-gradient(90deg, #5aa3ff, #2853ff); }
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

<div class="panel">
    <h2>Автокарта CRM-структуры</h2>
    <p>Автосканирование source/target, confidence, трансформации, конфликтные случаи и ручная корректировка.</p>
    <div class="actions">
        <button id="build-auto-map-btn">Build auto-map</button>
        <button id="export-map-btn">Export mapping config</button>
    </div>
    <div class="map-grid">
        <div>
            <table>
                <thead><tr><th>Source field</th><th>Target field</th><th>Types</th><th>Confidence</th><th>Rule</th><th>Status</th></tr></thead>
                <tbody id="field-mapping-table"></tbody>
            </table>
            <p class="small">Ambiguous cases автоматически уходят в needs review. Missing stages отмечаются как needs creation.</p>
        </div>
        <div>
            <h3>Explainability и риски</h3>
            <pre id="auto-map-explain">{}</pre>
            <h3>Stage/Enum coverage</h3>
            <pre id="auto-map-coverage">{}</pre>
        </div>
    </div>
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

<div class="panel">
    <h2>Migration Verification</h2>
    <p><span class="ok">✔ Verified entities: <b id="verified-count">0</b></span> &nbsp; <span class="warning">⚠ Warnings: <b id="warning-count">0</b></span> &nbsp; <span class="critical">✖ Critical issues: <b id="critical-count">0</b></span></p>
    <div class="grid">
        <div>
            <h3>Coverage</h3>
            <div class="chart" id="coverage-chart"></div>
        </div>
        <div>
            <h3>Integrity</h3>
            <div class="chart" id="integrity-chart"></div>
        </div>
    </div>
    <h3>Error distribution</h3>
    <div class="chart" id="error-chart"></div>
</div>

<div class="grid">
    <div class="panel"><h2>Сверка после миграции</h2><pre id="reconciliation-report">{}</pre></div>
    <div class="panel"><h2>Конфликты</h2><pre id="conflicts-report">[]</pre></div>
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
        <li>certification_report.json / .html / .pdf</li>
    </ul>
</div>

<script>
const source = {
    users: [{id: '1', email: 'owner@x.io'}],
    tasks: [{id: '10', responsible_id: '1', created_by: '1'}],
    deals: [{id: '77', title: 'Deal', stage_id: 'NEW', amount: 1200, company_id: '15'}],
    files: [{id: 'f1', checksum: 'abc', size: 42, mime: 'text/plain'}],
};
const target = { users: [{id: '1', email: 'owner@x.io'}], tasks: [{id: '10', responsible_id: '1', created_by: '1'}], deals: [{id: '77', title: 'Deal', stage_id: 'IN_PROGRESS', amount: 1200, company_id: '15'}], files: [{id: 'f1', checksum: 'abc', size: 42, mime: 'text/plain'}] };

const mockAutoMap = {
  version: 1,
  field_mappings: [
    {entity: 'crm_deals', source_field: 'PHONE', target_field: 'PHONE', source_type: 'string', target_type: 'string', confidence: 'high', score: 97, transformation_rule: 'none', status: 'auto', explain: 'Matched by exact_system_code, type_match'},
    {entity: 'crm_deals', source_field: 'UF_CRM_REGION', target_field: 'UF_CRM_REGION_NEW', source_type: 'string', target_type: 'string', confidence: 'medium', score: 64, transformation_rule: 'none', status: 'needs_review', explain: 'Matched by normalized_name + historical mapping'},
    {entity: 'crm_deals', source_field: 'BUDGET_TEXT', target_field: 'PROJECT_BUDGET', source_type: 'text', target_type: 'string', confidence: 'medium', score: 71, transformation_rule: 'text_to_string_truncate', status: 'needs_review', explain: 'Matched by semantic_match and type conversion'},
  ],
  stage_mappings: [
    {entity: 'crm_deals', source_stage: {name: 'Переговоры'}, target_stage: {name: 'Negotiation'}, confidence: 'medium', score: 68, status: 'needs_review'},
    {entity: 'crm_deals', source_stage: {name: 'В ожидании оплаты'}, target_stage: null, confidence: 'low', score: 22, status: 'needs_creation'}
  ],
  enum_mappings: [
    {entity: 'crm_deals', field: 'UF_CRM_SOURCE', source_value: 'Партнер', target_value: 'Partner', confidence: 'high', score: 92, status: 'auto'},
    {entity: 'crm_deals', field: 'UF_CRM_SOURCE', source_value: 'Неизвестно', target_value: 'unknown', confidence: 'low', score: 20, status: 'needs_creation'}
  ],
  conflicts: [
    {type: 'required_field_without_source', message: 'crm_deals.ASSIGNED_BY_ID is required in target but has no source mapping'},
    {type: 'precision_loss', message: 'crm_deals.BUDGET_TEXT may lose content by truncation'},
    {type: 'unmapped_stage', message: 'crm_deals.В ожидании оплаты missing in target'}
  ],
  summary: {field_coverage_percent: 33, stage_coverage_percent: 0, enum_coverage_percent: 50},
  dry_run: {
    errors: ['crm_deals.PROJECT_STAGE unresolved'],
    warnings: ['crm_deals.BUDGET_TEXT requires manual review'],
    coverage: {fields_percent: 33, stages_percent: 0, enums_percent: 50}
  }
};

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

const renderBar = (label, pct) => `<div>${label}: ${pct}%<div class="bar"><span style="width:${pct}%"></span></div></div>`;

const renderVerification = () => {
    const entities = ['users', 'tasks', 'deals', 'files'];
    let verified = 0;
    let warnings = 0;
    let critical = 0;

    for (const entity of entities) {
        const s = source[entity] ?? [];
        const t = new Map((target[entity] ?? []).map((r) => [r.id, r]));
        for (const row of s) {
            const targetRow = t.get(row.id);
            if (!targetRow) {
                critical++;
                continue;
            }
            if (entity === 'deals' && row.stage_id !== targetRow.stage_id) {
                warnings++;
            } else {
                verified++;
            }
        }
    }

    document.getElementById('verified-count').textContent = verified;
    document.getElementById('warning-count').textContent = warnings;
    document.getElementById('critical-count').textContent = critical;

    const total = verified + warnings + critical;
    const coverage = total ? Math.round((verified + warnings) / total * 100) : 0;
    const integrity = total ? Math.round((verified) / total * 100) : 0;

    document.getElementById('coverage-chart').innerHTML = renderBar('Verified+Warning coverage', coverage);
    document.getElementById('integrity-chart').innerHTML = renderBar('Strict integrity', integrity);
    document.getElementById('error-chart').innerHTML = [
        renderBar('Warnings', total ? Math.round(warnings / total * 100) : 0),
        renderBar('Critical', total ? Math.round(critical / total * 100) : 0)
    ].join('');
};

document.getElementById('dry-run-btn').addEventListener('click', () => {
    const plan = computePlan();
    const summary = plan.reduce((acc, item) => ((acc[item.action] = (acc[item.action] ?? 0) + 1), acc), {create: 0, update: 0, skip: 0, conflict: 0, manual_review: 0});
    document.getElementById('dry-run-kpis').innerHTML = Object.entries(summary).map(([key, val]) => `<div class="kpi"><b>${key}</b><div>${val}</div></div>`).join('');
    renderPlan(plan);
    document.getElementById('progress-entities').textContent = '40%';
    document.getElementById('progress-stages').textContent = 'mapping';
    document.getElementById('progress-current').textContent = plan[0]?.entity_type + ':' + plan[0]?.source_id;
    renderVerification();
});

document.getElementById('plan-filter').addEventListener('change', () => renderPlan(computePlan()));

document.getElementById('delta-preview-btn').addEventListener('click', () => {
    document.getElementById('delta-preview').textContent = 'found new: 2, changed: 1, conflicts: 0';
    document.getElementById('reconciliation-report').textContent = JSON.stringify({users: {source_count: 1, target_count: 1, status: 'OK'}, deals: {source_count: 1, target_count: 1, status: 'WARNING'}}, null, 2);
    document.getElementById('conflicts-report').textContent = JSON.stringify([{entity: 'deals', id: '77', issue: 'stage mismatch'}], null, 2);
    renderVerification();
});

document.getElementById('build-auto-map-btn').addEventListener('click', () => renderAutoMap(mockAutoMap));
document.getElementById('export-map-btn').addEventListener('click', () => {
    const blob = new Blob([JSON.stringify(mockAutoMap, null, 2)], {type: 'application/json'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'mapping-config.v1.json';
    a.click();
    URL.revokeObjectURL(url);
});
</script>
</body>
</html>
