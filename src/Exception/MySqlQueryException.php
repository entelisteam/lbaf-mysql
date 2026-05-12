<?php

namespace EntelisTeam\Lbaf\MySql\Exception;


use EntelisTeam\Lbaf\Exception\CustomException;
use EntelisTeam\Lbaf\Exception\LogLevelEnum;


class MySqlQueryException extends CustomException
{

    function __construct(public string $mySqlErrorMessage, public int $mySqlErrorNumber, public string $query)
    {
        parent::__construct(
            message: "SQL Query Error: #{$mySqlErrorNumber}: {$mySqlErrorMessage}" . PHP_EOL . "Query: {$this->query}",
            httpCode: 500,
            logLevel: LogLevelEnum::Critical,
            isError: true,
        );
    }


}