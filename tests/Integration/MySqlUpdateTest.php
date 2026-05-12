<?php
declare(strict_types=1);

namespace EntelisTeam\Lbaf\MySql\Tests\Integration;

use EntelisTeam\Lbaf\MySql\Exception\MySqlQueryException;

final class MySqlUpdateTest extends MySqlIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db->insert('fx_users', [
            ['email' => 'u1@example.com', 'name' => 'One',   'age' => 10],
            ['email' => 'u2@example.com', 'name' => 'Two',   'age' => 20],
            ['email' => 'u3@example.com', 'name' => 'Three', 'age' => 30],
        ]);
    }

    public function testUpdateWithStringWhereDoesNotEscapeWhere(): void
    {
        // Контракт: string $where передаётся в SQL как есть, апострофы внутри не экранируются.
        $result = $this->db->update(
            'fx_users',
            ['name' => 'NewOne'],
            "email = 'u1@example.com'",
        );
        $this->assertTrue($result);

        $row = $this->db
            ->query("SELECT name FROM fx_users WHERE email = 'u1@example.com'")
            ->firstRow();
        $this->assertSame('NewOne', $row->name);
    }

    public function testUpdateWithArrayWhereSingleValue(): void
    {
        $id = (int)$this->db
            ->query("SELECT id FROM fx_users WHERE email = 'u2@example.com'")
            ->firstRow()->id;

        $this->db->update('fx_users', ['name' => 'Renamed'], ['id' => $id]);

        $row = $this->db
            ->query('SELECT name FROM fx_users WHERE id = ' . $id)
            ->firstRow();
        $this->assertSame('Renamed', $row->name);
    }

    public function testUpdateSetsColumnToNull(): void
    {
        $this->db->update('fx_users', ['name' => null], ['email' => 'u1@example.com']);

        $row = $this->db
            ->query("SELECT name, name IS NULL AS is_null FROM fx_users WHERE email = 'u1@example.com'")
            ->firstRow();
        $this->assertSame(1, (int)$row->is_null);
        $this->assertNull($row->name);
    }

    public function testUpdateWithEmptyDataReturnsFalseAndDoesNotQuery(): void
    {
        $before = $this->snapshot();
        $result = $this->db->update('fx_users', [], ['email' => 'u1@example.com']);
        $this->assertFalse($result);
        $this->assertSame($before, $this->snapshot());
    }

    public function testUpdateWithEmptyWhereReturnsFalseAndDoesNotQuery(): void
    {
        $before = $this->snapshot();
        $result = $this->db->update('fx_users', ['name' => 'X'], '');
        $this->assertFalse($result);
        $this->assertSame($before, $this->snapshot());
    }

    public function testUpdateIgnoreSkipsRowsThatWouldViolateUnique(): void
    {
        // Пытаемся переставить email u1 на уже занятый u2 — без IGNORE это duplicate key.
        // updateIgnore должен не падать и оставить u1's email прежним.
        $result = $this->db->updateIgnore(
            'fx_users',
            ['email' => 'u2@example.com'],
            ['email' => 'u1@example.com'],
        );
        $this->assertTrue($result);

        $count = (int)$this->db
            ->query("SELECT COUNT(*) AS n FROM fx_users WHERE email = 'u1@example.com'")
            ->firstRow()->n;
        $this->assertSame(1, $count, 'u1 должен остаться (IGNORE пропустил конфликт)');
    }

    public function testUpdateWithoutIgnoreThrowsOnUniqueViolation(): void
    {
        $this->expectException(MySqlQueryException::class);
        $this->db->update(
            'fx_users',
            ['email' => 'u2@example.com'],
            ['email' => 'u1@example.com'],
        );
    }

    public function testGetAffectedRowsAfterUpdate(): void
    {
        $this->db->update('fx_users', ['name' => 'Bulk'], "age >= 20");
        $this->assertSame(2, $this->db->getAffectedRows());
    }

    public function testUpdateWithNumericKeyInArrayWhereIsKnownBug(): void
    {
        $this->markTestIncomplete(
            'Known bug: числовой ключ в array-where → logError("invalid where condition"), '
            . 'который тянет $this->mysqli->error/errno и склеивает мусорное сообщение. '
            . 'См. UNSOLVED_ISSUES.md.'
        );

        // Ожидание: внятное исключение про неверный where (без мусорного mysqli error attached).
        $this->expectException(MySqlQueryException::class);
        $this->db->update('fx_users', ['name' => 'X'], ['id' => 1, 'random-string-value']);
    }

    public function testUpdateWithArrayValueInArrayWhereIsKnownBug(): void
    {
        $this->markTestIncomplete(
            'Known bug: [\'col\' => [1,2,3]] строит "col=1 AND col=2 AND col=3" (всегда false) '
            . 'вместо ожидаемого "col IN (1,2,3)". См. UNSOLVED_ISSUES.md.'
        );

        $ids = $this->db
            ->query("SELECT id FROM fx_users ORDER BY id LIMIT 2")
            ->resultSingleFieldArray('id', 'integer');

        // Ожидаем что обе строки обновятся через IN(...).
        $this->db->update('fx_users', ['name' => 'BulkIn'], ['id' => $ids]);

        $affected = (int)$this->db
            ->query("SELECT COUNT(*) AS n FROM fx_users WHERE name = 'BulkIn'")
            ->firstRow()->n;
        $this->assertSame(2, $affected);
    }

    /**
     * @return array<string,mixed>
     */
    private function snapshot(): array
    {
        $rows = $this->db
            ->query('SELECT id, email, name, age FROM fx_users ORDER BY id')
            ->result();
        return array_map(static fn($r) => (array)$r, $rows);
    }
}
