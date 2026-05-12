<?php
declare(strict_types=1);

namespace EntelisTeam\Lbaf\MySql\Tests\Integration;

use EntelisTeam\Lbaf\MySql\MySql;
use EntelisTeam\Lbaf\MySql\MySqlConfig;
use PHPUnit\Framework\TestCase;

abstract class MySqlIntegrationTestCase extends TestCase
{
    protected MySql $db;

    protected function setUp(): void
    {
        $this->db = new MySql($this->testConfig());
        $this->db->transactionStart();
    }

    protected function tearDown(): void
    {
        $this->db->transactionRollback();
        $this->db->close();
    }

    protected function testConfig(): MySqlConfig
    {
        $password = getenv('LBAF_TEST_DB_PASSWORD');
        $config = new MySqlConfig(
            getenv('LBAF_TEST_DB_HOST') ?: '127.0.0.1',
            getenv('LBAF_TEST_DB_USER') ?: 'root',
            $password === false ? '' : $password,
            getenv('LBAF_TEST_DB_NAME') ?: 'lbaf_test',
            (int)(getenv('LBAF_TEST_DB_PORT') ?: 33306),
        );
        $config->setLazyConnect(false);
        return $config;
    }
}
