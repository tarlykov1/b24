<?php

declare(strict_types=1);

$localeDir = dirname(__DIR__, 4) . '/locales';
$enLocale = file_get_contents($localeDir . '/en.json') ?: '{}';
$ruLocale = file_get_contents($localeDir . '/ru.json') ?: '{}';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Bitrix24 Migration Admin</title>
    <style>
        body { font-family: sans-serif; margin: 2rem; color: #1f2937; }
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .menu { display: flex; gap: 1rem; font-weight: 600; }
        .lang-switcher { display: flex; gap: .25rem; align-items: center; }
        .lang-switcher button { border: 1px solid #d1d5db; background: #fff; padding: .25rem .5rem; cursor: pointer; }
        .lang-switcher button.active { background: #111827; color: #fff; }
        .panel { border: 1px solid #d1d5db; border-radius: .5rem; padding: 1rem; margin-bottom: 1rem; }
        .actions button { margin-right: .5rem; }
        .muted { color: #6b7280; }
        .status-chip { display: inline-block; background: #eff6ff; color: #1d4ed8; border-radius: 9999px; padding: .2rem .6rem; }
        .dashboard-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .75rem; }
        .kpi { background: #f9fafb; border-radius: .4rem; padding: .75rem; }
    </style>
</head>
<body>
<div class="topbar">
    <div>
        <h1 data-i18n="app.heading">Migration Control Panel</h1>
        <div class="menu">
            <span data-i18n="migration.menu.jobs">Jobs</span>
            <span data-i18n="migration.menu.settings">Settings</span>
        </div>
    </div>
    <div class="lang-switcher">
        <strong data-i18n="language.switcher">Language</strong>
        <button type="button" data-lang="ru" data-i18n="language.ru">RU</button>
        <span>|</span>
        <button type="button" data-lang="en" data-i18n="language.en">EN</button>
    </div>
</div>

<div class="panel"><h2 data-i18n="panel.preflight">Preflight</h2><p><span data-i18n="migration.status">Status</span>: <span class="status-chip" data-i18n="status.ready">Ready</span></p></div>
<div class="panel"><h2 data-i18n="panel.audit">Audit</h2><p><span data-i18n="migration.preview">Preview changes</span>: <span data-i18n="status.todo">TODO</span></p></div>
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
<div id="notification" class="muted"></div>

<script>
    const TRANSLATIONS = {
        en: <?= $enLocale ?>,
        ru: <?= $ruLocale ?>,
    };

    const DEFAULT_LOCALE = 'en';
    const STORAGE_KEY = 'migration.locale';

    const getLocale = () => {
        const locale = localStorage.getItem(STORAGE_KEY);
        return TRANSLATIONS[locale] ? locale : DEFAULT_LOCALE;
    };

    const t = (key, locale) => TRANSLATIONS[locale]?.[key] ?? TRANSLATIONS[DEFAULT_LOCALE]?.[key] ?? key;

    const formatMetrics = (locale) => {
        const numberFormatter = new Intl.NumberFormat(locale);
        const dateFormatter = new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'short' });

        document.getElementById('processedCount').textContent = numberFormatter.format(17342);
        document.getElementById('errorCount').textContent = numberFormatter.format(12);
        document.getElementById('lastSync').textContent = dateFormatter.format(new Date());
        document.getElementById('progressValue').textContent = numberFormatter.format(76.4) + '%';
    };

    const applyTranslations = (locale) => {
        document.documentElement.lang = locale;
        document.title = t('app.title', locale);

        document.querySelectorAll('[data-i18n]').forEach((element) => {
            const key = element.dataset.i18n;
            element.textContent = t(key, locale);
        });

        document.querySelectorAll('[data-message-key]').forEach((element) => {
            const key = element.dataset.messageKey;
            element.textContent = t(key, locale);
        });

        document.querySelectorAll('[data-lang]').forEach((button) => {
            button.classList.toggle('active', button.dataset.lang === locale);
        });

        formatMetrics(locale);
    };

    const setLocale = (locale) => {
        const nextLocale = TRANSLATIONS[locale] ? locale : DEFAULT_LOCALE;
        localStorage.setItem(STORAGE_KEY, nextLocale);
        applyTranslations(nextLocale);
        document.getElementById('notification').textContent = t('notifications.saved', nextLocale);
    };

    document.querySelectorAll('[data-lang]').forEach((button) => {
        button.addEventListener('click', () => setLocale(button.dataset.lang));
    });

    applyTranslations(getLocale());
</script>
</body>
</html>
