<?php

declare(strict_types=1);

namespace EntelisTeam\Lbaf\MySql\Rector;

/**
 * Реестр Rector-миграций пакета entelisteam/php-dto-hydrator.
 */
final class Migrations
{
    /**
     * @return list<class-string>
     */
    public static function all(): array
    {
        return [
            Migration\M20260512_0830_MySqlSplit::class,
        ];
    }
}
