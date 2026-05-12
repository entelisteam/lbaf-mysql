<?php
declare(strict_types=1);

namespace EntelisTeam\Lbaf\MySql\Tests\Integration;

use EntelisTeam\Lbaf\MySql\Exception\MySqlQueryException;
use stdClass;

final class MySqlInsertTest extends MySqlIntegrationTestCase
{
    public function testInsertSingleRowReturnsId(): void
    {
        $id = $this->db->insert('fx_users', [
            'email' => 'single@example.com',
            'name' => 'Single',
        ]);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $row = $this->db
            ->query('SELECT email FROM fx_users WHERE id = ' . $id)
            ->firstRow();
        $this->assertSame('single@example.com', $row->email);
    }

    public function testInsertBatchInsertsAllRowsAndReturnsFirstId(): void
    {
        $firstId = $this->db->insert('fx_users', [
            ['email' => 'batch-a@example.com', 'name' => 'A'],
            ['email' => 'batch-b@example.com', 'name' => 'B'],
            ['email' => 'batch-c@example.com', 'name' => 'C'],
        ]);

        $this->assertIsInt($firstId);
        $this->assertGreaterThan(0, $firstId);

        $count = (int)$this->db
            ->query("SELECT COUNT(*) AS n FROM fx_users WHERE email LIKE 'batch-%@example.com'")
            ->firstRow()->n;
        $this->assertSame(3, $count);
    }

    public function testInsertIgnoreOnDuplicateReturnsZero(): void
    {
        $first = $this->db->insertIgnore('fx_users', [
            'email' => 'dup@example.com',
            'name' => 'Original',
        ]);
        $this->assertGreaterThan(0, $first);

        $second = $this->db->insertIgnore('fx_users', [
            'email' => 'dup@example.com',
            'name' => 'Replacement',
        ]);
        $this->assertSame(0, $second);

        // Оригинальный name не перетёрт
        $row = $this->db
            ->query("SELECT name FROM fx_users WHERE email = 'dup@example.com'")
            ->firstRow();
        $this->assertSame('Original', $row->name);
    }

    public function testInsertUpdateWithoutFieldsBehavesAsInsert(): void
    {
        $id = $this->db->insertUpdate('fx_users', [
            'email' => 'iu-no-fields@example.com',
            'name' => 'NoFields',
        ]);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $row = $this->db
            ->query('SELECT email FROM fx_users WHERE id = ' . $id)
            ->firstRow();
        $this->assertSame('iu-no-fields@example.com', $row->email);
    }

    public function testInsertUpdateWithFieldsUpdatesOnlySpecifiedColumns(): void
    {
        $this->db->insert('fx_users', [
            'email' => 'iu-fields@example.com',
            'name' => 'OldName',
            'age' => 25,
        ]);

        $this->db->insertUpdate(
            'fx_users',
            ['email' => 'iu-fields@example.com', 'name' => 'NewName', 'age' => 99],
            ['name'],
        );

        $row = $this->db
            ->query("SELECT name, age FROM fx_users WHERE email = 'iu-fields@example.com'")
            ->firstRow();
        $this->assertSame('NewName', $row->name);
        $this->assertSame(25, (int)$row->age, 'age НЕ в $fields, должен остаться прежним');
    }

    public function testInsertUpdateExceptUpdatesAllButExcluded(): void
    {
        $this->db->insert('fx_users', [
            'email' => 'iue@example.com',
            'name' => 'OldName',
            'age' => 10,
        ]);

        $this->db->insertUpdateExcept(
            'fx_users',
            ['email' => 'iue@example.com', 'name' => 'NewName', 'age' => 20],
            ['email'],
        );

        $row = $this->db
            ->query("SELECT name, age FROM fx_users WHERE email = 'iue@example.com'")
            ->firstRow();
        $this->assertSame('NewName', $row->name);
        $this->assertSame(20, (int)$row->age);
    }

    public function testInsertUpdateExceptWithArrayOfObjects(): void
    {
        $a = new stdClass();
        $a->email = 'obj-a@example.com';
        $a->name = 'ObjA';

        $b = new stdClass();
        $b->email = 'obj-b@example.com';
        $b->name = 'ObjB';

        $result = $this->db->insertUpdateExcept('fx_users', [$a, $b], ['email']);
        $this->assertNotFalse($result);

        $count = (int)$this->db
            ->query("SELECT COUNT(*) AS n FROM fx_users WHERE email LIKE 'obj-%@example.com'")
            ->firstRow()->n;
        $this->assertSame(2, $count);
    }

    public function testInsertWithEmptyDataReturnsFalse(): void
    {
        $this->assertFalse($this->db->insert('fx_users', []));
        $this->assertFalse($this->db->insertUpdate('fx_users', []));
        $this->assertFalse($this->db->insertUpdateExcept('fx_users', []));
        $this->assertFalse($this->db->insertIgnore('fx_users', []));
    }

    public function testInsertWithInvalidNestedDataThrows(): void
    {
        $this->expectException(MySqlQueryException::class);
        $this->expectExceptionMessage('Invalid data to insert');

        $this->db->insert('fx_users', [[]]);
    }

    public function testInsertStoresNullAsRealNullNotString(): void
    {
        $id = $this->db->insert('fx_users', [
            'email' => 'null-name@example.com',
            'name' => null,
        ]);

        $row = $this->db
            ->query('SELECT name, name IS NULL AS is_null FROM fx_users WHERE id = ' . $id)
            ->firstRow();
        $this->assertSame(1, (int)$row->is_null);
        $this->assertNull($row->name);
    }

    public function testInsertEscapesQuotesBackslashesAndCyrillic(): void
    {
        $payload = "He said \"hi\" and 'bye'\\back\nslash // \\0 текст";

        $id = $this->db->insert('fx_users', [
            'email' => 'escape@example.com',
            'name' => $payload,
        ]);

        $row = $this->db
            ->query('SELECT name FROM fx_users WHERE id = ' . $id)
            ->firstRow();
        $this->assertSame($payload, $row->name);
    }

    public function testInsertHandlesNullByte(): void
    {
        $payload = "before\0after";

        $id = $this->db->insert('fx_users', [
            'email' => 'nullbyte@example.com',
            'name' => $payload,
        ]);

        $row = $this->db
            ->query('SELECT name FROM fx_users WHERE id = ' . $id)
            ->firstRow();
        $this->assertSame($payload, $row->name);
    }

    public function testInsertHandlesEmojisInUtf8mb4(): void
    {
        $payload = 'Hello 🐘🚀 мир';
        $id = $this->db->insert('fx_users', [
            'email' => 'emoji@example.com',
            'name' => $payload,
        ]);

        $row = $this->db
            ->query('SELECT name FROM fx_users WHERE id = ' . $id)
            ->firstRow();
        $this->assertSame($payload, $row->name);
    }

    public function testInsertUpdateReturnsTrueWhenFieldsSpecifiedEvenOnNewRowKnownBug(): void
    {
        // Зафиксированное поведение: при указанных $fields insertUpdate всегда
        // возвращает true, даже когда строка реально была вставлена.
        // Бага в UNSOLVED_ISSUES.md: insertUpdate возвращает true... — теряем insertId.
        // Тест сломается, когда баг починят — это и есть сигнал «обнови assertion».
        $result = $this->db->insertUpdate(
            'fx_users',
            ['email' => 'bug-fields@example.com', 'name' => 'Bug'],
            ['name'],
        );

        $this->assertTrue($result, 'Текущее поведение — true вместо insertId. См. UNSOLVED_ISSUES.md.');
    }
}
