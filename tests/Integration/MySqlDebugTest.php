<?php
declare(strict_types=1);

namespace EntelisTeam\Lbaf\MySql\Tests\Integration;

use EntelisTeam\Lbaf\MySql\MySql;
use ReflectionMethod;
use ReflectionProperty;

final class MySqlDebugTest extends MySqlIntegrationTestCase
{
    public function testDebugOffByDefaultDoesNotLogQueries(): void
    {
        $this->db->query('SELECT 1')->firstRow();
        $this->db->query('SELECT 2')->firstRow();

        $this->assertSame([], $this->logOf($this->db));
        $this->assertSame(0, $this->queryCountOf($this->db));
    }

    public function testSetDebugModeEnablesQueryLogging(): void
    {
        $this->db->setDebugMode(true);

        $this->db->query('SELECT 1')->firstRow();
        $this->db->query('SELECT 2')->firstRow();

        $log = $this->logOf($this->db);
        $this->assertCount(2, $log);
        $this->assertSame('SELECT 1', $log[0]->query);
        $this->assertSame('SELECT 2', $log[1]->query);
        $this->assertSame(2, $this->queryCountOf($this->db));
    }

    public function testSetDebugModeOnLiveConnectionEnablesProfiling(): void
    {
        $this->db->setDebugMode(true);

        $row = $this->db
            ->query("SHOW VARIABLES LIKE 'profiling'")
            ->firstRow();

        $this->assertNotNull($row);
        $this->assertSame('ON', $row->Value);
    }

    public function testLoadLogTimesAnnotatesLogEntriesWithDuration(): void
    {
        $this->db->setDebugMode(true);
        $this->db->query('SELECT 1')->firstRow();
        $this->db->query('SELECT 2')->firstRow();

        (new ReflectionMethod(MySql::class, 'loadLogTimes'))->invoke($this->db);

        $log = $this->logOf($this->db);
        $this->assertNotEmpty($log);
        $withTime = array_filter($log, static fn($e) => property_exists($e, 'time'));
        $this->assertNotEmpty($withTime, 'loadLogTimes должен заполнить ->time хотя бы у части записей');
    }

    /**
     * @return list<object>
     */
    private function logOf(MySql $db): array
    {
        return (new ReflectionProperty(MySql::class, 'log'))->getValue($db);
    }

    private function queryCountOf(MySql $db): int
    {
        return (new ReflectionProperty(MySql::class, 'query_count'))->getValue($db);
    }
}
