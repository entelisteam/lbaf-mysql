# CLAUDE.md

Behavioral guidelines to reduce common LLM coding mistakes. Merge with project-specific instructions as needed.

**Tradeoff:** These guidelines bias toward caution over speed. For trivial tasks, use judgment.

## 1. Think Before Coding

**Don't assume. Don't hide confusion. Surface tradeoffs.**

Before implementing:
- State your assumptions explicitly. If uncertain, ask.
- If multiple interpretations exist, present them - don't pick silently.
- If a simpler approach exists, say so. Push back when warranted.
- If something is unclear, stop. Name what's confusing. Ask.

## 2. Simplicity First

**Minimum code that solves the problem. Nothing speculative.**

- No features beyond what was asked.
- No abstractions for single-use code.
- No "flexibility" or "configurability" that wasn't requested.
- No error handling for impossible scenarios.
- If you write 200 lines and it could be 50, rewrite it.

Ask yourself: "Would a senior engineer say this is overcomplicated?" If yes, simplify.

## 3. Surgical Changes

**Touch only what you must. Clean up only your own mess.**

When editing existing code:
- Don't "improve" adjacent code, comments, or formatting.
- Don't refactor things that aren't broken.
- Match existing style, even if you'd do it differently.
- If you notice unrelated dead code, mention it - don't delete it.

When your changes create orphans:
- Remove imports/variables/functions that YOUR changes made unused.
- Don't remove pre-existing dead code unless asked.

The test: Every changed line should trace directly to the user's request.

## 4. Goal-Driven Execution

**Define success criteria. Loop until verified.**

Transform tasks into verifiable goals:
- "Add validation" → "Write tests for invalid inputs, then make them pass"
- "Fix the bug" → "Write a test that reproduces it, then make it pass"
- "Refactor X" → "Ensure tests pass before and after"

For multi-step tasks, state a brief plan:
```
1. [Step] → verify: [check]
2. [Step] → verify: [check]
3. [Step] → verify: [check]
```

Strong success criteria let you loop independently. Weak criteria ("make it work") require constant clarification.

---

**These guidelines are working if:** fewer unnecessary changes in diffs, fewer rewrites due to overcomplication, and clarifying questions come before implementation rather than after mistakes.


---
Когда делаешь git commit - пиши описание изменений по русски, кратко, не более 50 слов, но информативно.
Не указывай себя в качестве соавтора.

---

## Проект: entelisteam/lbaf-mysql

Тонкая обёртка над `mysqli` + встроенный SQL-билдер для INSERT/UPDATE. Извлечён из монорепо `lbaf`.

**Стек:** PHP ~8.2, ext-mysqli, PHPUnit 12. Зависимости: `entelisteam/lbaf-exception`.

**Структура:**
- `src/MySql.php` — основной класс. `src/MySqlConfig.php` — конфиг.
- `src/Exception/` — `MySqlConnectException`, `MySqlQueryException`.
- `tests/Unit/` — только `MySqlConfigTest` (парсинг host).
- `tests/Integration/` — против реальной MariaDB.

**Тесты:**
- `composer test:unit` — без БД.
- `composer db:up` → `composer test:integration` → `composer db:down` — против MariaDB 11 в Docker (порт `33306`, root без пароля, БД `lbaf_test`, tmpfs). `db:up`/`db:down` идемпотентны.
- `tests/bootstrap.php` ретраит подключение (20×0.5с), заливает `tests/Integration/schema.sql`, фейлит прогон если БД недоступна (не skip).
- Большинство integration-тестов наследуют `MySqlIntegrationTestCase` (транзакционная изоляция: `transactionStart` в setUp, `transactionRollback` в tearDown, `lazyConnect=false`). Исключение — `MySqlTransactionTest` (сам тестирует транзакции, extends `TestCase` напрямую, чистит данные по префиксу `tx-`).
- Env-переменные подключения: `LBAF_TEST_DB_{HOST,PORT,USER,PASSWORD,NAME}` — дефолты в `phpunit.xml.dist`.

**Известные баги:** `UNSOLVED_ISSUES.md`. Те, что воспроизведены тестами — помечены `markTestIncomplete` со ссылкой на этот файл.

**Будущие правки в src** и отложенный CI — см. `BACKLOG.md`.