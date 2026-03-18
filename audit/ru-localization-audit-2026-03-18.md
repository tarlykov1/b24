# Русская локализация — production-grade аудит (2026-03-18)

## 1) Executive verdict

**FAIL**

Русская локализация **не готова к production**. В проекте есть `locales/ru.json`, но он не подключён в рантайме; ключевые пользовательские поверхности (UI/Installer/CLI/API/Admin/Docs) в основном англоязычные или смешанные.

---

## 2) Summary

### Что проверено
- Локализационные словари (`locales/en.json`, `locales/ru.json`).
- Web entrypoint и admin/installer UI (`web/*`, `apps/migration-module/ui/admin/*`).
- API ответы и readiness/health/system-check потоки (`apps/migration-module/ui/admin/api.php`, `SystemCheckService`, `OperationsConsoleApi`).
- CLI (`bin/migration-module`, запуск `./bin/migration-module help`).
- Frontend console (`apps/migration-console/*`).
- Пользовательская документация (`README.md`, `docs/QUICKSTART.md`, `docs/installation-wizard.md`).

### Общая оценка качества
- **Сильные стороны**:
  - Файлы `locales/en.json` и `locales/ru.json` имеют одинаковое количество ключей (39/39), без рассинхронизации набора ключей.
  - Русские строки в `ru.json` в целом грамматически корректны.
- **Слабые стороны**:
  - Система i18n фактически не интегрирована: словарь есть, но не используется в UI/API/CLI.
  - Основной UX русскоязычного администратора строится на английских строках.
  - Installer и admin pages частично русские, частично английские (mixed language).
  - API/CLI ориентированы на англоязычные коды/сообщения без русской человеко-читаемой проекции.
  - Документация преимущественно англоязычная.

---

## 3) Findings by severity

## Critical

### C-01: Локализационные словари не подключены к фактическим интерфейсам
- **Severity:** Critical
- **Место:** `locales/ru.json`, `locales/en.json`, runtime UI/CLI/API
- **Исходный текст:** ключи `app.*`, `panel.*`, `migration.*`, `status.*` присутствуют в словарях, но в UI используются хардкод-строки.
- **Проблема:** i18n-слой декларативно существует, но не задействован. Для пользователя RU-локаль недоступна как рабочая опция.
- **Почему важно:** это системный дефект — локализация не масштабируется и не контролируется централизованно.
- **Рекомендация:**
  - Ввести единый i18n adapter для frontend + PHP UI + backend message mapping.
  - Подключить `locales/ru.json` через явный locale context.
  - Запретить новые пользовательские строки вне словарей (lint/CI rule).

### C-02: Основной admin UI и installer не русифицированы полноценно
- **Severity:** Critical
- **Место:** `apps/migration-module/ui/admin/index.php`, `apps/migration-module/ui/admin/install.php`, `web/index.php`
- **Исходный текст:** `Bitrix24 Migration Admin`, `Open MySQL Installation Wizard`, `MySQL Installer`, `Bitrix Migration Installer (MySQL-only)`, `MySQL connection settings`, `Migration Admin Unavailable`.
- **Проблема:** mixed RU/EN в одном экране; часть критических действий (installer flow) описана по-английски.
- **Почему важно:** installer/admin — first-run критический путь; англоязычные тексты повышают риск ошибок у русскоязычных администраторов.
- **Рекомендуемая формулировка (пример):**
  - `Bitrix24 Migration Admin` → `Панель управления миграцией Bitrix24`
  - `Open MySQL Installation Wizard` → `Открыть мастер установки MySQL`
  - `MySQL connection settings` → `Параметры подключения MySQL`

### C-03: Console UI (React) полностью англоязычный
- **Severity:** Critical
- **Место:** `apps/migration-console/index.html`, `apps/migration-console/src/components/Layout.tsx`, `apps/migration-console/src/pages/pages.tsx`
- **Исходный текст:** `Global Dashboard`, `Migration Jobs`, `Role Matrix`, `Approval Queue`, `Cutover Command Center`, `Latest events`, `Blockers / warnings` и др.
- **Проблема:** отсутствует русская локализация для всей frontend-консоли.
- **Почему важно:** это ключевая операционная поверхность; RU-администратор фактически вынужден работать на EN.
- **Рекомендация:** внедрить i18n в React (словари + locale switch + форматирование дат/чисел).

## High

### H-01: API возвращает только англоязычные/технические статусы без ru-message слоя
- **Severity:** High
- **Место:** `apps/migration-module/ui/admin/api.php`, `apps/migration-module/src/Application/Readiness/SystemCheckService.php`
- **Исходный текст:** `unsupported`, `unknown_endpoint`, `typed_confirmation_required`, `system_check_failed`, сообщения `DB_NAME and DB_USER are required`, `PDO MySQL extension is required`.
- **Проблема:** человеко-читаемые тексты на EN, без русской нормализованной витрины (`message_ru`).
- **Почему важно:** API используется installer/admin и влияет на UX диагностики.
- **Рекомендация:** оставить machine-safe `code` на EN, добавить локализуемое поле `message` по locale (`ru`/`en`) и документацию контракта.

### H-02: CLI вывод и ошибки не локализованы для русскоязычного оператора
- **Severity:** High
- **Место:** `bin/migration-module`
- **Исходный текст:** `Migration action blocked by preflight safety validator.`, `job_id_required`, англоязычные текстовые поля в error payload.
- **Проблема:** lifecycle CLI ориентирован на EN; русская операционная команда не получает RU-подсказок.
- **Почему важно:** CLI — production-интерфейс runbook-операций.
- **Рекомендация:** добавить `--locale=ru|en` и выдачу локализованного `human_message`, оставив `error_code` неизменным.

### H-03: Документация для установки и запуска преимущественно англоязычная
- **Severity:** High
- **Место:** `README.md`, `docs/QUICKSTART.md`, `docs/installation-wizard.md`
- **Исходный текст:** разделы и инструкции полностью на EN.
- **Проблема:** русскоязычный администратор не получает полноценной документации на RU.
- **Почему важно:** operational risk: неправильная интерпретация шагов внедрения.
- **Рекомендация:** двуязычная документация (RU-first или parity RU/EN), единый glossary и русские runbook-версии.

## Medium

### M-01: Терминологическая несогласованность RU/EN и калька в UI
- **Severity:** Medium
- **Место:** `locales/ru.json`, `web/index.php`, `apps/migration-module/ui/admin/index.php`
- **Исходный текст:** `Префлайт`, `runtime-конфигурацию`, `Migration admin временно недоступен`.
- **Проблема:** смешение англ. терминов и русской морфологии; tone неоднородный.
- **Почему важно:** снижает воспринимаемое качество продукта и ясность коммуникации.
- **Рекомендация:**
  - `Префлайт` → `Предварительная проверка` (или унифицированно `Preflight` как бренд-термин).
  - `runtime-конфигурацию` → `конфигурацию рантайма`.
  - `Migration admin...` → `Панель миграции...`.

### M-02: Неполная локализация installer step labels
- **Severity:** Medium
- **Место:** `apps/migration-module/ui/admin/install.php`
- **Исходный текст:** `environment_check`, `mysql_connection`, `schema_initialization` и т.д.
- **Проблема:** пользователю показываются служебные snake_case-идентификаторы вместо RU-лейблов шагов.
- **Почему важно:** ухудшение UX в критическом onboarding flow.
- **Рекомендация:** отобразить человеко-читаемые шаги: `Проверка окружения`, `Подключение к MySQL`, `Инициализация схемы`.

### M-03: Дефолт fallback locale = en без явного locale negotiation
- **Severity:** Medium
- **Место:** `BackendMessageTranslator`
- **Исходный текст:** `private const DEFAULT_LOCALE = 'en'`.
- **Проблема:** при любом сбое или неизвестной локали пользователь получает EN.
- **Почему важно:** русская локаль должна быть first-class для RU deployment.
- **Рекомендация:** поддержать конфигурируемый default locale + заголовок/параметр `locale` на всех поверхностях.

## Low

### L-01: Технические коды и machine-readable поля не отделены от UI-текстов
- **Severity:** Low
- **Место:** API/CLI payloads
- **Проблема:** в некоторых местах `message` смешивается с техническим контекстом; локализация может поломать automation, если не разделить поля.
- **Почему важно:** риск регрессии интеграций.
- **Рекомендация:** контракт: `error_code` (stable EN), `message` (localized), `details` (machine object).

---

## 4) Consistency review

### Несогласованные термины
- `Migration Admin` / `migration admin` / `Панель управления миграцией`.
- `Preflight` / `Префлайт` / `Предварительная проверка`.
- `Validation` / `Валидация` / `Проверка`.
- `Job` / `Задача`.
- `Cutover` / `Go-live` / `Финальное переключение`.
- `Runtime` / `рантайм` / `runtime`.

### Рекомендуемый глоссарий (унификация)
- migration → **миграция**
- preflight → **предварительная проверка**
- validation → **валидация** (для формальных проверок), **проверка** (для общих)
- readiness → **готовность**
- report → **отчёт**
- job → **задача миграции**
- stage/phase → **этап**
- warning/error/status → **предупреждение/ошибка/статус**
- source/target → **источник/целевая система**
- dry-run → **пробный запуск (dry-run)**
- preview → **предпросмотр**
- execute → **выполнить**
- resume → **продолжить**
- verify → **верификация** (или `проверка`, но единообразно)
- installer/setup → **мастер установки/первичная настройка**
- admin/runtime/API/CLI: оставить как технические термины, но с RU-пояснениями в UI.

---

## 5) Missing translations / fallback English

Найдены отсутствующие/неиспользуемые переводы и fallback-поведение:

1. **Все ключи `locales/ru.json` не используются в коде UI/API/CLI** (по сканированию использования ключей).
2. `web/index.php`: `Migration Admin Unavailable` и смешанный заголовок `Migration admin временно недоступен`.
3. `apps/migration-module/ui/admin/index.php`: title/h1/links полностью EN.
4. `apps/migration-module/ui/admin/install.php`: title/h1/subtitle EN, шаги в snake_case.
5. `apps/migration-console` (index + src): UI на EN, `lang="en"`.
6. `SystemCheckService`: human-readable ошибки EN (`DB_NAME and DB_USER are required`, `PDO MySQL extension is required`).
7. `README.md`, `docs/QUICKSTART.md`, `docs/installation-wizard.md`: инструкции EN-first.

---

## 6) Placeholder / formatting issues

### Что проверено
- Совпадение наборов ключей `en.json`/`ru.json`.
- Наличие/сохранность шаблонных токенов.

### Результат
- В текущих `locales/*.json` нет сложных placeholders (`%s`, `{name}`, ICU plural), поэтому прямых поломок подстановки **не обнаружено**.
- При этом системно отсутствует слой plural/locale formatting (даты/числа) в UI-консоли; отображаются сырые значения/ключи.

### Риски
- При вводе локализации без разделения machine/human полей возможно нарушение CLI/API контрактов.

---

## 7) Readiness by surface

- **UI:** **Not ready** — ключевые интерфейсы EN/mixed, i18n не подключён.
- **CLI:** **Not ready** — операторские сообщения EN, нет locale toggle.
- **API:** **Partially ready** — machine-readable коды есть, но человеко-читаемый RU-слой отсутствует.
- **Installer:** **Not ready** — mixed язык + служебные идентификаторы шагов.
- **Admin:** **Not ready** — dashboard/admin links EN.
- **Docs:** **Partially ready** — есть немного RU в отдельных местах, но базовые runbook/quickstart EN-first.

---

## 8) Final remediation plan

### Немедленно (blocking)
1. Подключить единый i18n runtime для web/admin/console.
2. Убрать хардкод строк из UI, перевести в словари.
3. Для API/CLI ввести двуслойный ответ: `error_code` (stable EN) + локализуемый `message`.
4. Локализовать installer critical path полностью (включая step labels).

### До релиза
1. Утвердить глоссарий терминов и провести терминологический рефактор.
2. Ввести CI-проверки:
   - запрет новых user-facing literal строк вне i18n;
   - детектор mixed-language UI;
   - проверка целостности ключей по локалям.
3. Добавить e2e-smoke для RU locale (UI + API + CLI).
4. Выпустить RU-версии `README/QUICKSTART/installation-wizard`.

### Можно отложить
1. Дополнительная стилистическая шлифовка tone-of-voice.
2. Расширенные правила типографики (кавычки, тире, locale-sensitive formatting).
3. Глубокая локализация аналитических/редких admin экранов после закрытия critical gap.
