<?php

namespace EntelisTeam\Lbaf\MySql;

use Exception;
use Generator;
use Lbaf\Helper\ConsoleTable;
use mysqli;
use mysqli_result;
use stdClass;
use Throwable;
use EntelisTeam\Lbaf\MySql\Exception\MySqlConnectException;
use EntelisTeam\Lbaf\MySql\Exception\MySqlQueryException;

/**
 * Класс работы с базой данных
 * @todo отсмотреть и описать все методы!
 */
class MySql
{

    /**
     * @var bool логировать ли запросы
     */
    public bool $debug = false;

    protected ?mysqli $mysqli = null;

    protected array $log = [];

    protected ?mysqli_result $res = null;

    protected int $query_count = 0;// Счётчик запросов к базе

    private MySqlConfig $config;

    function __construct(MySqlConfig $config)
    {
        $this->config = $config;

        $this->determineIfDebug();

        if (!$this->config->lazyConnect) {
            $this->connect();
        }
    }

    /**
     * Определяет, нужно ли логировать запросы в зависимости от окружения
     * @return void
     * @deprecated
     * @todo мне кажется какая-то дичь, нужно убрать и убрать в connect
     */
    protected function determineIfDebug(): self
    {

        $level = (defined('STDIN')) ? $this->config->logRequestsConsole : $this->config->logRequestsWebserver;
        //если соединение уже установлено и debug вдруг включили, нужно включить профилирование проверив что коннект живой
        if (!$this->debug && $level && isset($this->mysqli)) {
            $this->ping();
            $this->enableSqlProfiling();
        }
        return $this;
    }

    /**
     * Проверяет наличие подключения к базе, если нужно поднимает его
     * @todo подумать не воткнуть ли в self::query
     */
    public function ping(): self
    {
        try {
            if ($this->mysqli?->ping() !== true) {
                $this->connect();
            }
        } catch (Throwable $e) {
            $this->connect();
        }
        return $this;
    }

    /**
     * Функция подключения к базе. Специально объявлена protected, для доступа снаружи используйте ping
     * @return $this
     * @throws Exception
     */
    protected function connect(): self
    {
        $this->mysqli = new mysqli(
            ($this->config->usePersistentConnection ? 'p:' : '') . $this->config->host,
            $this->config->user,
            $this->config->password,
            $this->config->database,
            $this->config->port,
            $this->config->socket,
        );

        //@todo подумать правильно ли это
        if ($this->mysqli->connect_error) {
            //@todo убрать генерацию message в dbexception
            throw new MySqlConnectException($this->mysqli->connect_error, $this->mysqli->connect_errno);
        }

        if ($this->debug) {
            $this->enableSqlProfiling();
        }
        $this->mysqli->set_charset($this->config->encoding);

        return $this;
    }

    protected function enableSqlProfiling(): self
    {
        $this->mysqli->query('SET profiling_history_size = 100');
        $this->mysqli->query('SET profiling = 1');
        return $this;
    }

    /**
     * Execute sql query
     * @param string $qstring sql query string
     * @return self
     * @throws MySqlQueryException
     */
    function query(string $qstring): self
    {
        if (!isset($this->mysqli)) {
            $this->connect();
        }

        if ($this->debug) {
            $this->log[] = (object)array(
                'query' => $qstring,
            );
            $this->query_count++;
        }

        try {
            $success = $this->mysqli->real_query($qstring);
            $res = $this->mysqli->use_result();
        } catch (Throwable $e) {
            $this->logError($qstring);
        }
        $this->freeResult();
        if ($success === false) {
            $this->logError($qstring);
        } elseif ($res instanceof mysqli_result) {
            $this->res = $res;
        }

        //@todo это нужно перенести куда-то, например в freeResult()
        //сейчас это не будет работать т.к у нас незакрытый result висит
//        if ($this->debug && ($this->query_count % 100 === 0)) {
//            $this->loadLogTimes();
//        }

        return $this;
    }

    /**
     * Trigger Exception
     * @param string $qstring sql query string
     * @return string html formatted
     * @throws MySqlQueryException
     */
    protected function logError(string $qstring): void
    {
        throw new MySqlQueryException($this->mysqli->error, $this->mysqli->errno, $qstring);
    }

    public function freeResult(): self
    {
        $this?->res?->free_result();
        $this->res = null;
        return $this;
    }

    /**
     * Принудительно устанавливает режим логирования
     * @param bool $level
     * @return $this
     * @todo подумать нужно ли вообще
     */
    public function setDebugMode(bool $level): self
    {
        if (!$this->debug && $level && isset($this->mysqli)) {
            $this->ping();
            //@todo странная копипаста
            $this->enableSqlProfiling();
        }

        $this->debug = $level;

        return $this;
    }

    /**
     * Разрывает подключение с базой данных
     */
    public function close(): self
    {
        if ($this->debug) {
            $this->loadLogTimes();
        }
        $this->freeResult();
        $this->mysqli->close();
        $this->mysqli = null;
        return $this;
    }

    /**
     * Загружает данные по запросам
     * @return void
     */
    protected function loadLogTimes()
    {
        if (isset($this->mysqli)) {
            $res = $this->mysqli->query('SHOW PROFILES');
            $i = ($this->query_count >= 100) ? ($this->query_count - 100) : 0;
            while ($x = $res->fetch_object()) {
                if (isset($this->log[$i])) $this->log[$i]->time = $x->Duration;
                $i++;
            }
        }
    }

    /**
     * @return object[]
     */
    public function result(?string $key = null): array
    {
        return $this->_result('stdClass', $key);
    }

    /**
     * @return object[]
     */
    protected function _result(string $class = 'stdClass', ?string $field = null): array
    {
        $tmp = array();
        while ($x = $this->res?->fetch_object($class)) {
            if ($field !== null) {
                $tmp[$x->$field] = $x;
            } else {
                $tmp[] = $x;
            }
        }
        $this->freeResult();
        return $tmp;
    }

    /**
     * Возвращает массив объектов
     * @param string $class Имя класс для заполнения. Класс должен быть без конструктора!
     * @param ?string $field Если указан, то значение из этого параметра объекта будет использовано как ключ массива
     * @return object[]
     */
    public function resultObjects(string $class = 'stdClass', ?string $field = null): array
    {
        return $this->_result($class, $field);
    }

    /**
     * Возвращает генератор
     * @param string $class Имя класс для заполнения. Класс должен быть без конструктора!
     * @param ?string $field Если указан, то значение из этого параметра будет передано в генератор как итератор
     */
    function resultGen(string $class = 'stdClass', ?string $field = null): Generator
    {
        while ($x = $this->res?->fetch_object($class)) {
            if ($field !== null) {
                yield [$x->$field, $x];
            } else {
                yield $x;
            }
        }
        $this->freeResult();
    }

    /**
     * Возвращает одномерный массив заполненные значениями поля $field
     * @param string $field
     * @param string $type [string, integer, float]
     */
    public function fill(string $field, ?string $type = null): array
    {
        return $this->resultSingleFieldArray($field, $type);
    }

    /**
     * Возвращает одномерный массив заполненные значениями поля $field, ключи по возрастанию
     * @param string $field
     * @param ?string $type [string, integer, float]
     */
    public function resultSingleFieldArray(string $field, ?string $type = null): array
    {
        $tmp = [];
        while ($x = $this->res?->fetch_object()) {
            $tmp[] = match ($type) {
                'string' => (string)$x->$field,
                'integer' => (integer)$x->$field,
                'float' => (float)$x->$field,
                default => $x->$field,
            };
        }
        $this->freeResult();
        return $tmp;
    }

    /**
     * Возвращает одномерный массив, заполненные значениями поля $field, ключи поле keyField
     * @param string $field
     * @param string $keyField
     * @param ?string $type [string, integer, float]
     */
    function resultSingleFieldAssoc(string $field, string $keyField, ?string $type = null)
    {
        $tmp = [];
        while ($x = $this->res?->fetch_object()) {
            $tmp[$x->$keyField] = match ($type) {
                'string' => (string)$x->$field,
                'integer' => (integer)$x->$field,
                'float' => (float)$x->$field,
                default => $x->$field,
            };
        }
        $this->freeResult();
        return $tmp;
    }

    /**
     * Возвращает первый кортеж из ответа в виде объекта
     * @param string $class Имя класс для заполнения. Класс должен быть без конструктора!
     */
    public function firstRow(string $class = 'stdClass'): object|null
    {
        $result = $this->res?->fetch_object($class) ?: null;
        $this->freeResult();
        return $result;
    }

    /**
     * Выводит табличку с логом запросов
     * @param float $bad_time max sql query time
     * @return void
     */
    public function printLog(float $bad_time = 0.1): void
    {
        $log = $this->getLog();

        if (defined('STDIN')) {
            //консоль
            $tmp = [];
            $i = 0;
            $totalTime = 0;
            foreach ($log as $item) {
                $tmp[] = [
                    'id' => ++$i,
                    'query' => $item->query,
                    'time' => $item->time,
                    'total time' => ($totalTime += $item->time),
                ];
            }

            echo ConsoleTable::fromArray($tmp);
        } else {
            //web
            $totalTime = 0;
            echo '<table style="font-face:courier new; font-size:14px; border: 1px solid grey;border-collapse: collapse">';
            $i = 1;
            foreach ($log as $item) {
                echo '<tr style="border-bottom:1px solid grey"><td style="width:20px">', $i++, '</td><td>', $item->query, '</td><td>', (($item->time >= $bad_time) ? '<b style="color:red">' . $item->time . '</b>' : $item->time), '</td></tr>';
                $totalTime += $item->time;
            }
            echo '<tr><td></td><td><b>Total: ', count($log), ' queries</b></td><td><b>' . $totalTime . '</b></td></tr>';
            echo '</table>';
        }
    }

    /**
     *  Дозагрузка лога запросов через mysqli
     */
    protected function getLog(): array
    {
        $this->loadLogTimes();
        return $this->log;
    }

    /**
     * Формирование и выполнение запроса INSERT
     * @param string $table название таблицы для вставки
     * @param mixed $data массив данных для вставки, eg: [$field=>$value, ...] либо массив массивов/объектов для вставки
     * @return mixed id если вставлена запись, false в случае фейла
     */
    public function insert($table, $data): bool|int
    {
        return $this->insertUpdate($table, $data, []);
    }

    /**
     * Формирование и выполнение запроса INSERT ... ON DUPLICATE KEY UPDATE SET ...
     * @param string $table название таблицы для вставки
     * @param mixed $data массив данных для вставки, eg: [$field=>$value, ...] либо массив массивов/объектов для вставки
     * @param array $fields массив полей которые НУЖНО обновлять, eg: [$field1, $field2, ...]. Если не указан - запрос превращается в обычный insert
     * @return bool|int true если не пустой fields, id если вставлена запись, false в случае фейла
     * @todo исправить логику ответа
     */
    public function insertUpdate($table, array $data, array $fields = []): bool|int
    {
        if (count($data)) {

            $tmp = $this->structureDataInformation($data);
            if (!count($tmp->columns)) {
                //@todo подумать над типом исключения
                throw new MySqlQueryException('Invalid data to insert', 0, '');
            }

            $update_fields = [];
            if (count($fields)) {
                foreach ($fields as $column) {
                    $update_fields[] = '`' . $column . '` = VALUES(`' . $column . '`)';
                }
            }

            $sqlq = 'INSERT INTO `' . $this->sqlEscape($table) . '`
                    (' . join(', ', $tmp->columns) . ')
                VALUES
                    ' . join(', ', $tmp->value_trains) . '
                ';
            if (count($update_fields)) {
                $sqlq .= ' ON DUPLICATE KEY UPDATE ' . join(', ', $update_fields);
            }

            $this->query($sqlq);

            //@todo fixme плохое поведение - true на самом деле возвращается если указаны поля для обновления
            $result = (count($update_fields)) ? true : ($this->insertId());
            $this->freeResult();
            return $result;
        }
        return false;

    }

    /**
     * Разбор входного массива SQL запросов
     * @todo добавить проверку на одинаковые поля во всех объектах массива
     */
    protected function structureDataInformation($data): object
    {
        $columns = [];
        $value_trains = [];
        if (is_array($data) && (is_array(reset($data)) || is_object(reset($data)))) { // в $data лежит массив сущностей для вставки
            if (count($data)) {
                //собираем столбцы на основе первого объекта
                $tmp = reset($data);
                foreach ($tmp as $column => $value) {
                    $columns[] = '`' . $this->sqlEscape($column) . '`';
                }
                unset($tmp);

                //собираем данные для вставки
                foreach ($data as $item) {
                    $item_values = [];
                    foreach ($item as $value) {
                        $item_values[] = is_null($value) ? 'NULL' : ('"' . $this->sqlEscape($value) . '"');
                    }
                    $value_trains[] = '(' . join(',', $item_values) . ')';
                }
            }
        } else { //в $data лежит одна сущность для вставки
            $item_values = [];

            //собираем столбцы и данные в один проход
            foreach ($data as $column => $value) {
                $columns[] = '`' . $this->sqlEscape($column) . '`';
                $item_values[] = is_null($value) ? 'NULL' : ('"' . $this->sqlEscape($value) . '"');
            }
            $value_trains[] = '(' . join(',', $item_values) . ')';
        }
        return (object)[
            'columns' => $columns,
            'value_trains' => $value_trains,
        ];
    }

    /**
     * Экранирует значение переменной
     * @param $string
     * @return string
     * @todo подумать - может быть воткнуть сюда ping - если вызвано когда коннект умер все сломается
     * @todo сделать отдельную функцию которая учитывает тип поля string/numeric и добавляет кавычки
     */
    public function sqlEscape($string): string
    {
        if (!isset($this->mysqli)) {
            $this->connect();
        }

        return $this->mysqli->real_escape_string($string);
    }

    /**
     * last insert id
     * @return int
     * @todo посмотреть где используется
     * @todo посмотреть откуда там string в типе ответа
     */
    function insertId()
    {
        return $this->mysqli->insert_id;
    }

    function insertIgnore($table, $data): bool|int
    {

        $tmp = $this->structureDataInformation($data);
        if (!count($tmp->columns)) {
            return false;
        }

        $this->query('
            INSERT IGNORE INTO
                `' . $this->sqlEscape($table) . '`
                (' . join(', ', $tmp->columns) . ')
            VALUES
                ' . join(', ', $tmp->value_trains) . '
        ');
        unset($tmp);
        $result = $this->insertId();
        $this->freeResult();
        return $result;
    }

    /**
     * Формирование и выполнение запроса INSERT ... ON DUPLICATE KEY UPDATE SET ...
     * @param string $table название таблицы для вставки
     * @param mixed $data массив данных для вставки, eg: [$field=>$value, ...] либо массив массивов/объектов для вставки
     * @param array $do_not_update массив полей которые НЕ нужно обновлять, eg: [$field1, $field2, ...]
     * @return mixed id если вставлена запись, true если данные были обновлены, false в случае фейла
     */
    function insertUpdateExcept($table, array $data, array $do_not_update = []): bool|int
    {
        if (count($data)) {
            $fields = [];

            if (is_array($data) && (is_array(reset($data)) || is_object(reset($data)))) {
                $data_tmp = reset($data); //теперь в $data_tmp одиночный объект для вставки
            } else {
                $data_tmp = $data;
            }

            foreach ($data_tmp as $column => $value) {
                if (!in_array($column, $do_not_update)) {
                    $fields[] = $column;
                }
            }
            return $this->insertUpdate($table, $data, $fields);
        }
        return false;

    }

    /**
     * Обновляет данные в таблице с игнорированием
     * @param string $table Название таблицы для обновления данных
     * @param array|stdClass $data структура вида ключ=>значение. Ключ будет использован как название колонки
     * @param string|array $where условие для обновления. string НЕ ЭКРАНИРУЕТСЯ!
     * @param bool $ignore делать ли UPDATE IGNORE
     * @return bool
     * @todo изменить возвращаемый тип, например на int affected_rows
     */
    function updateIgnore(string $table, $data, string|array $where)
    {
        return $this->update($table, $data, $where, true);
    }

    /**
     * Обновляет данные в таблице
     * @param string $table Название таблицы для обновления данных
     * @param array|stdClass $data структура вида ключ=>значение. Ключ будет использован как название колонки
     * @param string|array $where условие для обновления. string НЕ ЭКРАНИРУЕТСЯ!
     * @param bool $ignore делать ли UPDATE IGNORE
     * @return bool
     * @todo изменить возвращаемый тип, например на int affected_rows
     */
    function update(string $table, $data, string|array $where, bool $ignore = false): bool
    {

        if (is_array($where)) {
            $tmp = [];
            foreach ($where as $column => $value) {
                if (!is_string($column)) {
                    $this->logError('db: invalid where condition');
                }
                if (!is_array($value)) {
                    //['id' => 123]
                    $tmp[] = '`' . $this->sqlEscape($column) . '` = ' . (is_null($value) ? 'NULL' : '"' . $this->sqlEscape($value) . '"');
                } else {
                    //['id' => [1,2,3]]
                    foreach ($value as $realvalue) {
                        $tmp[] = '`' . $this->sqlEscape($column) . '` = ' . (is_null($realvalue) ? 'NULL' : '"' . $this->sqlEscape($realvalue) . '"');
                    }
                }
            }
            $where = join(' AND ', $tmp);
        }

        if (
            (
                (is_array($data) && count($data))
                ||
                (is_object($data) && count((array)$data))
            )
            &&
            trim($where) !== ''
        ) {
            $set = [];
            foreach ($data as $column => $value) {
                $set[] = '`' . $this->sqlEscape($column) . '` = ' . (is_null($value) ? 'NULL' : '"' . $this->sqlEscape($value) . '"');
            }

            $this->query('
                UPDATE ' . ($ignore ? 'IGNORE' : '') . '
                    `' . $this->sqlEscape($table) . '`
                SET
                    ' . implode(', ', $set) . '
                WHERE ' . $where . '
            ');
            $this->freeResult();
            return true;
        }
        return false;
    }

    public function getAffectedRows(): int
    {
        return $this->mysqli->affected_rows;
    }

    public function transactionStart(): self
    {
        $this->mysqli->query('START TRANSACTION');
        return $this;
    }

    public function transactionCommit(): self
    {
        $this->mysqli->query('COMMIT');
        return $this;
    }

    public function transactionRollback(): self
    {
        $this->mysqli->query('ROLLBACK');
        return $this;
    }
}
