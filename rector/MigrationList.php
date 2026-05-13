<?php

declare(strict_types=1);

namespace EntelisTeam\Lbaf\MySql\Rector;

use EntelisTeam\Lbaf\MySql\Rector\Migration\Migration_20260512_0830_MySqlSplit;
use EntelisTeam\Lbaf\Rector\RectorMigrationListInterface;

/**
 * Реестр Rector-миграций пакета entelisteam/php-dto-hydrator.
 */
final class MigrationList implements RectorMigrationListInterface
{
    /**
     * @return list<class-string>
     */
    public static function all(): array
    {
        return [
            Migration_20260512_0830_MySqlSplit::class,
        ];
    }
}
