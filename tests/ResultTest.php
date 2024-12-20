<?php

/**
 * Converter of complex PostgreSQL types and an OO wrapper for PHP's pgsql extension
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-wrapper/master/LICENSE
 *
 * @package   sad_spirit\pg_wrapper
 * @copyright 2014-2024 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 *
 * @noinspection SqlResolve
 */

declare(strict_types=1);

namespace sad_spirit\pg_wrapper\tests;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_wrapper\{
    Connection,
    converters,
    exceptions\OutOfBoundsException
};

/**
 * Unit test for ResultSet class
 */
class ResultTest extends TestCase
{
    protected static Connection $conn;

    public static function setUpBeforeClass(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            return;
        }
        self::$conn = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);
        self::$conn->execute('drop table if exists test_result');
        self::$conn->execute('create table test_result (one integer, two text, three text)');
        self::$conn->execute(<<<SQL
insert into test_result values
    (1, 'foo', 'first value of foo'),
    (3, 'foo', 'second value of foo'),
    (5, 'bar', 'first value of bar'),
    (7, 'bar', 'second value of bar'),
    (9, 'baz', 'only value of baz')
SQL
        );
    }

    public function setUp(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
    }

    public function testAffectedRows(): void
    {
        $resultDML = self::$conn->execute("insert into test_result values (10, 'last', 'this is temporary')");
        $this::assertEquals(1, $resultDML->getAffectedRows());
        $this::assertCount(0, $resultDML);

        $resultReturning = self::$conn->execute("delete from test_result where one = 10 returning *");
        $this::assertEquals(1, $resultReturning->getAffectedRows());
        $this::assertCount(1, $resultReturning);
    }

    public function testSetMode(): void
    {
        $res = self::$conn->execute("select * from test_result where one = 1");
        $this->assertEquals(['one' => 1, 'two' => 'foo', 'three' => 'first value of foo'], $res[0]);

        $res->setMode(\PGSQL_NUM);
        $this->assertEquals([1, 'foo', 'first value of foo'], $res[0]);
    }

    public function testSetType(): void
    {
        $res = self::$conn->execute("select row(one, three) as onethree from test_result where two = 'baz'");
        $this->assertEquals('(9,"only value of baz")', $res[0]['onethree']);

        $res->setType('onethree', new converters\containers\CompositeConverter(
            ['a' => new converters\IntegerConverter(), 'b' => new converters\StringConverter()]
        ));
        $this->assertEquals(['a' => 9, 'b' => 'only value of baz'], $res[0]['onethree']);
    }

    public function testSetTypeMissingFieldName(): void
    {
        $this::expectException(OutOfBoundsException::class);

        $res = self::$conn->execute("select one, two from test_result");
        $res->setType('three', new converters\StubConverter());
    }

    public function testSetTypeMissingFieldIndex(): void
    {
        $this::expectException(OutOfBoundsException::class);

        $res = self::$conn->execute("select one, two from test_result");
        $res->setType(3, new converters\StubConverter());
    }

    public function testFetchColumn(): void
    {
        $res = self::$conn->execute("select one, two from test_result where one > 5");

        $this->assertEquals([7, 9], $res->fetchColumn(0));
        $this->assertEquals(['bar', 'baz'], $res->fetchColumn('two'));
    }

    public function testFetchColumnMissingFieldName(): void
    {
        $this::expectException(OutOfBoundsException::class);

        $res = self::$conn->execute("select one, two from test_result");
        $res->fetchColumn('three');
    }

    public function testFetchColumnMissingFieldIndex(): void
    {
        $this::expectException(OutOfBoundsException::class);

        $res = self::$conn->execute("select one, two from test_result");
        $res->fetchColumn(3);
    }

    public function testFetchAll(): void
    {
        $res = self::$conn->execute("select one, two from test_result where one > 5 order by one");

        $this->assertEquals(
            [
                ['one' => 7, 'two' => 'bar'],
                ['one' => 9, 'two' => 'baz']
            ],
            $res->fetchAll(\PGSQL_ASSOC)
        );

        $this->assertEquals(
            [
                [7, 'bar'],
                [9, 'baz']
            ],
            $res->fetchAll(\PGSQL_NUM)
        );
    }

    public function testFetchAllUsingKeyWithTwoColumns(): void
    {
        $res = self::$conn->execute("select one, two from test_result where one < 9 order by one");

        $this->assertEquals(
            [
                'foo' => 3,
                'bar' => 7
            ],
            $res->fetchAll(null, 'two')
        );

        $this->assertEquals(
            [
                1 => ['foo'],
                3 => ['foo'],
                5 => ['bar'],
                7 => ['bar']
            ],
            $res->fetchAll(\PGSQL_NUM, 'one', true)
        );

        $this->assertEquals(
            [
                'foo' => [1, 3],
                'bar' => [5, 7]
            ],
            $res->fetchAll(null, 1, false, true)
        );
    }

    public function testFetchAllUsingKey(): void
    {
        $res = self::$conn->execute("select * from test_result where one > 1 order by one");

        $this->assertEquals(
            [
                3 => ['foo', 'second value of foo'],
                5 => ['bar', 'first value of bar'],
                7 => ['bar', 'second value of bar'],
                9 => ['baz', 'only value of baz']
            ],
            $res->fetchAll(\PGSQL_NUM, 'one')
        );

        $this->assertEquals(
            [
                'foo' => [
                    ['one' => 3, 'three' => 'second value of foo']
                ],
                'bar' => [
                    ['one' => 5, 'three' => 'first value of bar'],
                    ['one' => 7, 'three' => 'second value of bar']
                ],
                'baz' => [
                    ['one' => 9, 'three' => 'only value of baz']
                ]
            ],
            $res->fetchAll(\PGSQL_ASSOC, 1, false, true)
        );
    }

    public function testConfiguresTypeConverterArgumentUsingConnection(): void
    {
        $mockTimestampOne = $this->createMock(converters\datetime\TimeStampTzConverter::class);
        $mockTimestampTwo = $this->createMock(converters\datetime\TimeStampTzConverter::class);

        $mockTimestampOne->expects($this->once())
            ->method('setConnection');

        $mockTimestampTwo->expects($this->once())
            ->method('setConnection');

        $res = self::$conn->execute(
            "select now() as tztest, now() + '1 day'::interval as moretest",
            ['tztest' => $mockTimestampOne]
        );
        $res->setType(1, $mockTimestampTwo);
    }

    public function testRemembersLastReadRow(): void
    {
        $mockString = $this->createMock(converters\StringConverter::class);

        $mockString->expects($this->once())
            ->method('input')
            ->willReturn('not foo');

        $res = self::$conn->execute("select * from test_result where one = 1");
        $res->setType('two', $mockString);
        $this::assertEquals(1, $res[0]['one']);
        $this::assertEquals('not foo', $res[0]['two']);
        $this::assertEquals('first value of foo', $res[0]['three']);
    }

    public function testGetTableOID(): void
    {
        $result = self::$conn->execute("select two, 'two' from test_result where one = 1");

        $this::assertIsNumeric($result->getTableOID('two'));
        $this::assertNull($result->getTableOID(1));

        $this::expectException(OutOfBoundsException::class);
        $this::expectExceptionMessage('is not present');
        $result->getTableOID('foo');
    }

    public function testNumericAlias(): void
    {
        $result = self::$conn->execute('select \'foo\' as "1", \'bar\' as "0"');

        $this::assertEquals(['foo'], $result->fetchColumn('1'));
        $this::assertEquals(['bar'], $result->fetchColumn(1));
    }

    public function testIterateColumn(): void
    {
        $res = self::$conn->execute("select one, two from test_result where one > 5");

        $this::assertEquals([7, 9], \iterator_to_array($res->iterateColumn('one')));
        $this::assertEquals(['bar', 'baz'], \iterator_to_array($res->iterateColumn(1)));
    }

    public function testIterateAssociative(): void
    {
        $res = self::$conn->execute("select one, two from test_result where one > 5 order by one");

        $res->setMode(\PGSQL_NUM);
        $this::assertEquals(
            [
                ['one' => 7, 'two' => 'bar'],
                ['one' => 9, 'two' => 'baz']
            ],
            \iterator_to_array($res->iterateAssociative())
        );
    }

    public function testIterateNumeric(): void
    {
        $res = self::$conn->execute("select one, two from test_result where one > 5 order by one");

        $res->setMode(\PGSQL_ASSOC);
        $this::assertEquals(
            [
                [7, 'bar'],
                [9, 'baz']
            ],
            \iterator_to_array($res->iterateNumeric())
        );
    }

    public function testIterateKeyedAssociativeTwoColumns(): void
    {
        $res = self::$conn->execute("select one, two from test_result where one > 5 order by one");

        $this::assertEquals(
            [7 => 'bar', 9 => 'baz'],
            \iterator_to_array($res->iterateKeyedAssociative())
        );
        $this::assertEquals(
            ['bar' => 7, 'baz' => 9],
            \iterator_to_array($res->iterateKeyedAssociative('two'))
        );
        $this::assertEquals(
            ['bar' => ['one' => 7], 'baz' => ['one' => 9]],
            \iterator_to_array($res->iterateKeyedAssociative('two', true))
        );
    }

    public function testIterateAssociativeThreeColumns(): void
    {
        $res      = self::$conn->execute("select one, two, three from test_result where one < 5 order by one");
        $expected = [
            1 => ['two' => 'foo', 'three' => 'first value of foo'],
            3 => ['two' => 'foo', 'three' => 'second value of foo']
        ];

        $this::assertEquals($expected, \iterator_to_array($res->iterateKeyedAssociative('one')));
        $this::assertEquals($expected, \iterator_to_array($res->iterateKeyedAssociative(null, true)));
    }

    public function testIterateKeyedNumericTwoColumns(): void
    {
        $res = self::$conn->execute("select one, two from test_result where one > 5 order by one");

        $this::assertEquals(
            [7 => 'bar', 9 => 'baz'],
            \iterator_to_array($res->iterateKeyedNumeric())
        );
        $this::assertEquals(
            ['bar' => 7, 'baz' => 9],
            \iterator_to_array($res->iterateKeyedNumeric(1))
        );
        $this::assertEquals(
            ['bar' => [7], 'baz' => [9]],
            \iterator_to_array($res->iterateKeyedNumeric(1, true))
        );
    }

    public function testIterateNumericThreeColumns(): void
    {
        $res      = self::$conn->execute("select one, two, three from test_result where one < 5 order by one");
        $expected = [
            1 => ['foo', 'first value of foo'],
            3 => ['foo', 'second value of foo']
        ];

        $this::assertEquals($expected, \iterator_to_array($res->iterateKeyedNumeric(0)));
        $this::assertEquals($expected, \iterator_to_array($res->iterateKeyedNumeric(0, true)));
    }
}
