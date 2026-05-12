<?php
declare(strict_types=1);

namespace EntelisTeam\Lbaf\MySql\Tests\Integration;

use EntelisTeam\Lbaf\MySql\Exception\MySqlQueryException;
use EntelisTeam\Lbaf\MySql\MySql;
use mysqli;
use ReflectionProperty;

final class MySqlQueryTest extends MySqlIntegrationTestCase
{
    public function testSuccessfulSelectReturnsResult(): void
    {
        $row = $this->db
            ->query('SELECT 1 AS v, "ok" AS s')
            ->firstRow();

        $this->assertNotNull($row);
        $this->assertSame(1, (int)$row->v);
        $this->assertSame('ok', $row->s);
    }

    public function testInvalidSqlThrowsQueryExceptionWithDetails(): void
    {
        $badQuery = 'SELECT FROM WHERE not a query';

        try {
            $this->db->query($badQuery);
            $this->fail('Expected MySqlQueryException was not thrown');
        } catch (MySqlQueryException $e) {
            $this->assertNotSame(0, $e->mySqlErrorNumber);
            $this->assertNotSame('', $e->mySqlErrorMessage);
            $this->assertSame($badQuery, $e->query);
        }
    }

    public function testQueryAutoConnectsWhenLazy(): void
    {
        $config = $this->testConfig();
        $config->setLazyConnect(true);
        $lazyDb = new MySql($config);

        $mysqliRef = new ReflectionProperty(MySql::class, 'mysqli');
        $this->assertNull($mysqliRef->getValue($lazyDb));

        $row = $lazyDb->query('SELECT 1 AS v')->firstRow();

        $this->assertSame(1, (int)$row->v);
        $this->assertInstanceOf(mysqli::class, $mysqliRef->getValue($lazyDb));

        $lazyDb->close();
    }

    public function testTwoSequentialQueriesWithFullFetchInBetween(): void
    {
        $first = $this->db->query('SELECT 1 AS v')->firstRow();
        $second = $this->db->query('SELECT 2 AS v')->firstRow();

        $this->assertSame(1, (int)$first->v);
        $this->assertSame(2, (int)$second->v);
    }

    public function testSecondQueryWorksWhenFirstResultWasNotFetched(): void
    {
        $this->markTestIncomplete(
            'Known bug: freeResult() не дренирует курсор use_result(), '
            . 'второй query() падает с "Commands out of sync (2014)". '
            . 'См. UNSOLVED_ISSUES.md.'
        );

        $this->db->query('SELECT 1 AS v UNION SELECT 2 UNION SELECT 3');
        $row = $this->db->query('SELECT 42 AS v')->firstRow();
        $this->assertSame(42, (int)$row->v);
    }
}
