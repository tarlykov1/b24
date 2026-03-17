# Portable Offline Deployment (MySQL-only)

## Release artifact content
Архив поставки должен уже содержать:
- application code (`apps/`, `bin/`, `web/`, `config/`, `db/`, `docs/`);
- `vendor/` (без запуска `composer install` на сервере);
- prebuilt static assets (если используются UI bundles);
- шаблоны конфигурации (`migration.config.yml`, `config/generated-install-config.json` optional).

## Offline constraints
- Интернет не требуется и не проверяется.
- Не выполняются `composer install`, `npm install`, `apt/yum/apk`, `curl/wget`.
- Runtime backend только MySQL (`pdo_mysql`).

## Quick deploy
1. Скопировать архив на целевой сервер.
2. Распаковать в `/b24_migration`.
3. Настроить веб-сервер на `/b24_migration/web`.
4. Открыть `/` (или `/installer.php`/`/install`).
5. Ввести `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` (+ optional charset/collation).
6. Нажать проверку подключения и инициализацию схемы.
7. Сохранить конфиг и перейти в UI миграции.

## Preconfigured mode
Если в архиве уже есть `config/generated-install-config.json` с валидным `mysql` блоком,
приложение запускается сразу и installer пропускается.

## Minimal server requirements
- PHP >= 8.1
- extensions: `pdo`, `pdo_mysql`, `json`, `mbstring`
- сеть до MySQL host:port
- DB grants для схемы migration platform: `SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP`
