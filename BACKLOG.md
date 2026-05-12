# Backlog

Открытые задачи, не привязанные к конкретному багу (баги — в `UNSOLVED_ISSUES.md`).

## Рефакторинг под pure-unit тесты SQL-билдера

Сейчас `structureDataInformation`, `insert`/`insertUpdate`/`update` слитно строят SQL и сразу его выполняют через живой `mysqli`. Из-за этого построение SQL нельзя покрыть быстрыми юнитами без БД.

### 1. Вынести escaper параметром

`structureDataInformation` зовёт `$this->sqlEscape()`, который требует `mysqli`. Передать escaper-callable аргументом: `structureDataInformation($data, callable $escape)`. Правка одной сигнатуры protected-метода, обратная совместимость сохраняется.

Альтернатива — `EscaperInterface` с `MysqliEscaper`/`IdentityEscaper`. Чище, но больше движений; делать только если callable станет тесно.

### 2. Разделить build и execute

`insert`/`insertUpdate`/`update` → `buildInsertSql(...)` (возвращает строку) + `query($sql)`. Откроет юниты на:
- сортировку колонок (есть `@todo` в коде);
- структуру `VALUES` / `ON DUPLICATE KEY UPDATE` / `UPDATE IGNORE`;
- NULL-обработку;
- структуру `WHERE` (включая баг с array-where из `UNSOLVED_ISSUES.md`).

Рефакторинг на ~30 строк, окупается кратно.

После §1+§2 написать `StructureDataInformationTest` (pure-unit): одиночный массив, массив массивов, массив объектов, NULL, пустые данные, assert на готовую SQL-строку для каждого варианта.
