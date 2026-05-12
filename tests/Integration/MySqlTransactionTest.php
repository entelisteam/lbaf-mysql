<?php
declare(strict_types=1);

namespace EntelisTeam\Lbaf\MySql\Tests\Integration;

use EntelisTeam\Lbaf\MySql\MySql;
use EntelisTeam\Lbaf\MySql\MySqlConfig;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Transaction-тесты НЕ наследуются от MySqlIntegrationTestCase: тот оборачивает
 * каждый тест в транзакцию, что ломает сценарии start/commit/rollback под тестом.
 * Очистка — вручную по префиксу email 'tx-'.
 */
final class MySqlTransactionTest extends TestCase
{
    private MySql $db;

    protected function setUp(): void
    {
        $this->db = new MySql($this->testConfig());
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        try {
            $this->cleanup();
        } catch (Throwable) {
            // соединение могло умереть, не маскируем основную ошибку теста
        }
        $this->db->close();
    }

    public function testStartRollbackDiscardsInsertedRow(): void
    {
        $this->db->transactionStart();
        $this->db->insert('fx_users', ['email' => 'tx-rollback@example.com', 'name' => 'Rb']);
        $this->db->transactionRollback();

        $this->assertSame(0, $this->countByEmail('tx-rollback@example.com'));
    }

    public function testStartCommitPersistsInsertedRow(): void
    {
        $this->db->transactionStart();
        $this->db->insert('fx_users', ['email' => 'tx-commit@example.com', 'name' => 'Cm']);
        $this->db->transactionCommit();

        $this->assertSame(1, $this->countByEmail('tx-commit@example.com'));
    }

    public function testNestedTransactionStartImplicitlyCommitsOuter(): void
    {
        // MySQL/MariaDB не поддерживают настоящие вложенные транзакции:
        // повторный START TRANSACTION неявно коммитит текущую.
        // Этот тест фиксирует именно это поведение.

        $this->db->transactionStart();
        $this->db->insert('fx_users', ['email' => 'tx-nested-outer@example.com', 'name' => 'Outer']);

        $this->db->transactionStart(); // implicit COMMIT внешней
        $this->db->insert('fx_users', ['email' => 'tx-nested-inner@example.com', 'name' => 'Inner']);

        $this->db->transactionRollback(); // откатывает только внутреннюю

        $this->assertSame(1, $this->countByEmail('tx-nested-outer@example.com'), 'Outer закоммитился неявно');
        $this->assertSame(0, $this->countByEmail('tx-nested-inner@example.com'), 'Inner откатился');
    }

    private function countByEmail(string $email): int
    {
        return (int)$this->db
            ->query("SELECT COUNT(*) AS n FROM fx_users WHERE email = '" . $email . "'")
            ->firstRow()->n;
    }

    private function cleanup(): void
    {
        $this->db->query("DELETE FROM fx_users WHERE email LIKE 'tx-%'");
    }

    private function testConfig(): MySqlConfig
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
