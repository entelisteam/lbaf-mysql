<?php

namespace EntelisTeam\Lbaf\MySql;

class MySqlConfig
{
    public string $host;
    public string $user;
    public string $password;
    public string $database;
    public int $port = 3306;
    public ?string $socket;


    /**
     * @var bool Логировать ли запросы при вызове из web сервера
     * @todo убрать отсюда
     */
    public bool $logRequestsWebserver = true;

    /**
     * @var bool Логировать ли запросы при вызове из консоли
     * @todo убрать отсюда
     */
    public bool $logRequestsConsole = false;

    /**
     * @var string Кодировка в формате SET NAMES ...
     */
    public string $encoding = 'utf8mb4';

    /**
     * @var bool Устанавливать соединение с базой при первом запросе, а не при инициализации.
     * Экономит время работы скриптов которые не используют базу
     */
    public bool $lazyConnect = true;

    /**
     * @var bool Использовать постоянные соединения.
     * WARNING - Библиотека не делает автоматический cleanup.
     */
    public bool $usePersistentConnection = false;


    /**
     * @param string $host host to connect. You can pass hostname:port or p:hostname:port as well
     * @param string $user
     * @param string $password
     * @param string $database
     * @param int $port
     * @param ?string $socket
     */
    public function __construct(string $host, string $user, string $password, string $database, int $port = 3306, ?string $socket = null)
    {
        //magic, allows using host like 'p:hostname'
        if (substr($host, 0, 2) === 'p:') {
            $this->usePersistentConnection = true;
            $host = substr($host, 2);
        }

        //magic, allows using host like 'hostname:3333' etc
        $tmp = explode(':', $host);
        if (count($tmp) === 2) {
            [$host, $port] = $tmp;
        }

        $this->host = $host;
        $this->user = $user;
        $this->password = $password;
        $this->database = $database;
        $this->port = $port;
        $this->socket = $socket;
    }

    public function setSocketConnection(string $socket): self
    {
        $this->host = 'localhost';
        $this->socket = $socket;
        return $this;
    }

    public function setLazyConnect(bool $useLazyConnect): self
    {
        $this->lazyConnect = $useLazyConnect;
        return $this;
    }

    public function setPersistentConnection(bool $usePersistentConnection): self
    {
        $this->usePersistentConnection = $usePersistentConnection;
        return $this;
    }

    public function setEncoding(string $encoding): self
    {
        $this->encoding = $encoding;
        return $this;
    }

}
