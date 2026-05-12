<?php
declare(strict_types=1);

namespace EntelisTeam\Lbaf\MySql\Tests\Integration;

use Generator;
use stdClass;

final class MySqlFetchTest extends MySqlIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db->insert('fx_users', [
            ['email' => 'a@example.com', 'name' => 'Alice', 'age' => 30, 'score' => '10.50'],
            ['email' => 'b@example.com', 'name' => 'Bob',   'age' => 40, 'score' => '20.25'],
            ['email' => 'c@example.com', 'name' => 'Carol', 'age' => 50, 'score' => '30.00'],
        ]);
    }

    public function testResultReturnsAllRowsIndexed(): void
    {
        $rows = $this->db
            ->query('SELECT email FROM fx_users ORDER BY email')
            ->result();

        $this->assertCount(3, $rows);
        $this->assertSame([0, 1, 2], array_keys($rows));
        $this->assertSame('a@example.com', $rows[0]->email);
    }

    public function testResultUsesGivenFieldAsKey(): void
    {
        $rows = $this->db
            ->query('SELECT email, name FROM fx_users ORDER BY email')
            ->result('email');

        $this->assertCount(3, $rows);
        $this->assertSame(['a@example.com', 'b@example.com', 'c@example.com'], array_keys($rows));
        $this->assertSame('Bob', $rows['b@example.com']->name);
    }

    public function testResultObjectsHydratesIntoGivenClass(): void
    {
        $rows = $this->db
            ->query('SELECT email, name FROM fx_users ORDER BY email')
            ->resultObjects(FetchFixture::class);

        $this->assertCount(3, $rows);
        $this->assertContainsOnlyInstancesOf(FetchFixture::class, $rows);
        $this->assertSame('Alice', $rows[0]->name);
        $this->assertSame('a@example.com', $rows[0]->email);
    }

    public function testResultObjectsWithFieldUsesItAsKey(): void
    {
        $rows = $this->db
            ->query('SELECT email, name FROM fx_users ORDER BY email')
            ->resultObjects(FetchFixture::class, 'email');

        $this->assertSame(['a@example.com', 'b@example.com', 'c@example.com'], array_keys($rows));
        $this->assertSame('Carol', $rows['c@example.com']->name);
    }

    public function testResultGenYieldsObjectsLazily(): void
    {
        $gen = $this->db
            ->query('SELECT email FROM fx_users ORDER BY email')
            ->resultGen();

        $this->assertInstanceOf(Generator::class, $gen);
        $emails = [];
        foreach ($gen as $row) {
            $emails[] = $row->email;
        }
        $this->assertSame(['a@example.com', 'b@example.com', 'c@example.com'], $emails);
    }

    public function testResultGenWithFieldYieldsPairs(): void
    {
        $gen = $this->db
            ->query('SELECT email, name FROM fx_users ORDER BY email')
            ->resultGen(stdClass::class, 'email');

        $collected = [];
        foreach ($gen as [$key, $row]) {
            $collected[$key] = $row->name;
        }
        $this->assertSame(
            ['a@example.com' => 'Alice', 'b@example.com' => 'Bob', 'c@example.com' => 'Carol'],
            $collected,
        );
    }

    public function testFirstRowReturnsNullOnEmptyResult(): void
    {
        $row = $this->db
            ->query("SELECT id FROM fx_users WHERE email = 'nonexistent@example.com'")
            ->firstRow();

        $this->assertNull($row);
    }

    public function testFirstRowReturnsObjectOnNonEmpty(): void
    {
        $row = $this->db
            ->query("SELECT email, name FROM fx_users WHERE email = 'a@example.com'")
            ->firstRow();

        $this->assertNotNull($row);
        $this->assertSame('Alice', $row->name);
    }

    public function testResultSingleFieldArrayWithoutTypeReturnsStrings(): void
    {
        $values = $this->db
            ->query('SELECT age FROM fx_users ORDER BY age')
            ->resultSingleFieldArray('age');

        // mysqli без MYSQLI_OPT_INT_AND_FLOAT_NATIVE отдаёт numeric как строки
        $this->assertSame(['30', '40', '50'], $values);
    }

    public function testResultSingleFieldArrayWithIntegerTypeCasts(): void
    {
        $values = $this->db
            ->query('SELECT age FROM fx_users ORDER BY age')
            ->resultSingleFieldArray('age', 'integer');

        $this->assertSame([30, 40, 50], $values);
    }

    public function testResultSingleFieldArrayWithFloatTypeCasts(): void
    {
        $values = $this->db
            ->query('SELECT score FROM fx_users ORDER BY score')
            ->resultSingleFieldArray('score', 'float');

        $this->assertSame([10.5, 20.25, 30.0], $values);
    }

    public function testResultSingleFieldArrayWithStringTypeCasts(): void
    {
        $values = $this->db
            ->query('SELECT age FROM fx_users ORDER BY age')
            ->resultSingleFieldArray('age', 'string');

        $this->assertSame(['30', '40', '50'], $values);
    }

    public function testResultSingleFieldAssocUsesKeyField(): void
    {
        $map = $this->db
            ->query('SELECT email, age FROM fx_users ORDER BY age')
            ->resultSingleFieldAssoc('age', 'email', 'integer');

        $this->assertSame(
            ['a@example.com' => 30, 'b@example.com' => 40, 'c@example.com' => 50],
            $map,
        );
    }

    public function testFillIsAliasOfResultSingleFieldArray(): void
    {
        $viaFill = $this->db
            ->query('SELECT age FROM fx_users ORDER BY age')
            ->fill('age', 'integer');
        $viaPrimary = $this->db
            ->query('SELECT age FROM fx_users ORDER BY age')
            ->resultSingleFieldArray('age', 'integer');

        $this->assertSame($viaPrimary, $viaFill);
    }
}

final class FetchFixture
{
    public string $email = '';
    public ?string $name = null;
}
