<?php

declare(strict_types=1);

namespace EntelisTeam\Lbaf\MySql\Rector\Migration;

use Rector\Configuration\RectorConfigBuilder;
use Rector\Renaming\Rector\Name\RenameClassRector;

/**
 * Миграция для downstream-проектов: переход с Lbaf-овских namespace'ов на отдельный пакет
 */
final class Migration_20260512_0830_MySqlSplit
{
    /**
     * Применяет правила миграции к существующему конфигуратору.
     */
    public static function apply(RectorConfigBuilder $config): RectorConfigBuilder
    {
        return $config
            ->withConfiguredRule(RenameClassRector::class, [
                'Lbaf\\Database\\MySql' => 'EntelisTeam\\Lbaf\\MySql\\MySql',
                'Lbaf\\Database\\MySqlConfig' => 'EntelisTeam\\Lbaf\\MySql\\MySqlConfig',
                'Lbaf\\Database\\MySqlConnectException' => 'EntelisTeam\\Lbaf\\MySql\\Exception\\MySqlConnectException',
                'Lbaf\\Database\\MySqlQueryException' => 'EntelisTeam\\Lbaf\\MySql\\Exception\\MySqlQueryException',
            ])

            //импортируем короткие имена через use вместо FQN, удаляем устаревшие use на Lbaf-овские классы
            ->withImportNames(importNames: true, importDocBlockNames: true, importShortClasses: false, removeUnusedImports: true);
    }


}
