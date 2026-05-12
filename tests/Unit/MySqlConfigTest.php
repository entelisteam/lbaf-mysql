<?php
declare(strict_types=1);

namespace  EntelisTeam\Lbaf\MySql\Tests\Unit;

use EntelisTeam\Lbaf\MySql\MySqlConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MySqlConfigTest extends TestCase
{
    public static function hostsProvider(): array
    {
        return [
            ['inputHost' => '127.0.0.1', 'expectedHost' => '127.0.0.1', 'expectedPersistent' => false, 'expectedPort' => 3306],
            ['inputHost' => 'p:127.0.0.1', 'expectedHost' => '127.0.0.1', 'expectedPersistent' => true, 'expectedPort' => 3306],
            ['inputHost' => '127.0.0.1:1000', 'expectedHost' => '127.0.0.1', 'expectedPersistent' => false, 'expectedPort' => 1000],
            ['inputHost' => 'p:127.0.0.1:1000', 'expectedHost' => '127.0.0.1', 'expectedPersistent' => true, 'expectedPort' => 1000],
            ['inputHost' => 'mydomain.com', 'expectedHost' => 'mydomain.com', 'expectedPersistent' => false, 'expectedPort' => 3306],
            ['inputHost' => 'mydomain.com:1000', 'expectedHost' => 'mydomain.com', 'expectedPersistent' => false, 'expectedPort' => 1000],
            ['inputHost' => 'p:mydomain.com', 'expectedHost' => 'mydomain.com', 'expectedPersistent' => true, 'expectedPort' => 3306],
            ['inputHost' => 'p:mydomain.com:1000', 'expectedHost' => 'mydomain.com', 'expectedPersistent' => true, 'expectedPort' => 1000],
        ];
    }

    #[DataProvider('hostsProvider')]
    public function testStatic(string $inputHost, string $expectedHost, bool $expectedPersistent, int $expectedPort): void
    {
        $config = new MySqlConfig($inputHost, 'root', '', '');

        $this->assertEquals($expectedHost, $config->host, 'check host');
        $this->assertEquals($expectedPersistent, $config->usePersistentConnection, 'check persistent');
        $this->assertEquals($expectedPort, $config->port, 'check port');
    }

    #[DataProvider('hostsProvider')]
    public function testManualEdit(string $inputHost, string $expectedHost, bool $expectedPersistent, int $expectedPort): void
    {
        $config = (new MySqlConfig($inputHost, 'root', '', ''))->setPersistentConnection(false);

        $this->assertEquals($expectedHost, $config->host, 'check host');
        $this->assertEquals(false, $config->usePersistentConnection, 'check persistent');
        $this->assertEquals($expectedPort, $config->port, 'check port');

        $config = (new MySqlConfig($inputHost, 'root', '', ''))->setPersistentConnection(true);

        $this->assertEquals($expectedHost, $config->host, 'check host');
        $this->assertEquals(true, $config->usePersistentConnection, 'check persistent');
        $this->assertEquals($expectedPort, $config->port, 'check port');

    }


}
