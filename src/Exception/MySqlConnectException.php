<?php

namespace EntelisTeam\Lbaf\MySql\Exception;

use EntelisTeam\Lbaf\Exception\CustomException;
use EntelisTeam\Lbaf\Exception\LogLevelEnum;

class MySqlConnectException extends CustomException
{

    function __construct(public string $mySqlErrorMessage, public int $mySqlErrorNumber)
    {
        parent::__construct(
            message: "SQL Query Error: #{$mySqlErrorNumber}: {$mySqlErrorMessage}",
            httpCode: 500,
            logLevel: LogLevelEnum::Alert,
        );
    }


}