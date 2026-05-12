<?php
declare(strict_types=1);

namespace EntelisTeam\Lbaf\MySql\Tests\Integration;

use EntelisTeam\Lbaf\MySql\Exception\MySqlConnectException;
use EntelisTeam\Lbaf\MySql\MySql;
use EntelisTeam\Lbaf\MySql\MySqlConfig;
use mysqli;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class MySqlConnectionTest extends TestCase
{
    private ?MySql $db = null;

    protected function tearDown(): void
    {
        if ($this->db !== null) {
            try {
                $this->db->close();
            } catch (\Throwable) {
                // соединение могло быть уже убито
            }
            $this->db = null;
        }
    }

    public function testLazyConnectDoesNotConnectInConstructor(): void
    {
        $config = $this->config();
        $config->setLazyConnect(true);
        $this->db = new MySql($config);

        $this->assertNull($this->mysqliOf($this->db));
    }

    public function testEagerConnectConnectsInConstructor(): void
    {
        $config = $this->config();
        $config->setLazyConnect(false);
        $this->db = new MySql($config);

        $this->assertInstanceOf(mysqli::class, $this->mysqliOf($this->db));
    }

    public function testFirstQueryTriggersConnectWhenLazy(): void
    {
        $config = $this->config();
        $config->setLazyConnect(true);
        $this->db = new MySql($config);
        $this->assertNull($this->mysqliOf($this->db));

        $this->db->query('SELECT 1');

        $this->assertInstanceOf(mysqli::class, $this->mysqliOf($this->db));
    }

    #[WithoutErrorHandler]
    public function testWrongCredentialsThrowConnectException(): void
    {
        $config = new MySqlConfig(
            $this->env('LBAF_TEST_DB_HOST', '127.0.0.1'),
            'definitely-not-a-user',
            'wrong-password',
            $this->env('LBAF_TEST_DB_NAME', 'lbaf_test'),
            (int)$this->env('LBAF_TEST_DB_PORT', '33306'),
        );
        $config->setLazyConnect(false);

        $this->expectException(MySqlConnectException::class);
        @new MySql($config);
    }

    public function testPingRevivesKilledConnection(): void
    {
        $config = $this->config();
        $config->setLazyConnect(false);
        $this->db = new MySql($config);

        $connId = (int)$this->db
            ->query('SELECT CONNECTION_ID() AS id')
            ->firstRow()
            ->id;

        $side = $this->sideMysqli();
        $side->query('KILL ' . $connId);
        $side->close();

        $this->db->ping();

        $row = $this->db->query('SELECT 1 AS v')->firstRow();
        $this->assertSame(1, (int)$row->v);
        $this->assertNotSame($connId, (int)$this->db->query('SELECT CONNECTION_ID() AS id')->firstRow()->id);
    }

    public function testSetCharsetApplied(): void
    {
        $config = $this->config();
        $config->setLazyConnect(false);
        $config->setEncoding('utf8mb4');
        $this->db = new MySql($config);

        $row = $this->db
            ->query("SHOW VARIABLES LIKE 'character_set_client'")
            ->firstRow();

        $this->assertNotNull($row);
        $this->assertSame('utf8mb4', $row->Value);
    }

    public function testPersistentConnectionPrefixWorks(): void
    {
        $config = new MySqlConfig(
            'p:' . $this->env('LBAF_TEST_DB_HOST', '127.0.0.1'),
            $this->env('LBAF_TEST_DB_USER', 'root'),
            $this->envOrEmpty('LBAF_TEST_DB_PASSWORD'),
            $this->env('LBAF_TEST_DB_NAME', 'lbaf_test'),
            (int)$this->env('LBAF_TEST_DB_PORT', '33306'),
        );
        $config->setLazyConnect(false);
        $this->db = new MySql($config);

        $this->assertTrue($config->usePersistentConnection);
        $row = $this->db->query('SELECT 1 AS v')->firstRow();
        $this->assertSame(1, (int)$row->v);
    }

    private function config(): MySqlConfig
    {
        return new MySqlConfig(
            $this->env('LBAF_TEST_DB_HOST', '127.0.0.1'),
            $this->env('LBAF_TEST_DB_USER', 'root'),
            $this->envOrEmpty('LBAF_TEST_DB_PASSWORD'),
            $this->env('LBAF_TEST_DB_NAME', 'lbaf_test'),
            (int)$this->env('LBAF_TEST_DB_PORT', '33306'),
        );
    }

    private function sideMysqli(): mysqli
    {
        return new mysqli(
            $this->env('LBAF_TEST_DB_HOST', '127.0.0.1'),
            $this->env('LBAF_TEST_DB_USER', 'root'),
            $this->envOrEmpty('LBAF_TEST_DB_PASSWORD'),
            $this->env('LBAF_TEST_DB_NAME', 'lbaf_test'),
            (int)$this->env('LBAF_TEST_DB_PORT', '33306'),
        );
    }

    private function mysqliOf(MySql $db): ?mysqli
    {
        return (new ReflectionProperty(MySql::class, 'mysqli'))->getValue($db);
    }

    private function env(string $name, string $default): string
    {
        $v = getenv($name);
        return $v === false || $v === '' ? $default : $v;
    }

    private function envOrEmpty(string $name): string
    {
        $v = getenv($name);
        return $v === false ? '' : $v;
    }
}
