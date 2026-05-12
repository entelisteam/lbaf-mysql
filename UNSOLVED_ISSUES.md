## MySql
- [ ] `printLog()` в CLI ветке зовёт `Lbaf\Helper\ConsoleTable::fromArray(...)`, но этого класса нет в зависимостях standalone-пакета (остаток от lbaf monorepo). Любой вызов `printLog()` из консоли упадёт class-not-found. Решения: вынести ConsoleTable как отдельный пакет/зависимость, либо удалить эту ветку и оставить простой `echo` / json.
- [ ] query() с непрочитанным результатом ломает следующий query() — `Commands out of sync (errno 2014)`. `freeResult()` зовёт `mysqli_result::free_result()`, но это не дренирует курсор `use_result()` на проводе. Воспроизводится тестом `MySqlQueryTest::testSecondQueryWorksWhenFirstResultWasNotFetched` (помечен `markTestIncomplete`). Фикс: либо `store_result()` вместо `use_result()`, либо явный `while ($res->fetch_row())` перед `free_result()`.
- [ ] update с ['col' => [1,2,3]] строит col=1 AND col=2 AND col=3 — всегда false. Должно быть IN(...) или хотя бы OR.
- [ ] update с числовым ключом в array-where зовёт logError('db: invalid where condition'), который пытается достать $this->mysqli->error/errno (их там нет) → побочное мусорное сообщение в exception.
- [ ] printLog() в web-ветке выводит ```<table>``` вместо ```</table>``` (строка 375).
- [ ] insertUpdate возвращает true даже на новой вставке когда указаны $fields — теряем insertId (есть @todo).
- [ ] close() зануляет ```$this->mysqli```, но повторный close() упадёт на ```$this->mysqli->close()```. Минор.
- [ ] Флаги подключения (использование MYSQLI_CLIENT_COMPRESS например)