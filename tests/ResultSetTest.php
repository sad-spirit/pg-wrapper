<?php
/**
 * Wrapper for PHP's pgsql extension providing conversion of complex DB types
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-wrapper/master/LICENSE
 *
 * @package   sad_spirit\pg_wrapper
 * @copyright 2014-2017 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

namespace sad_spirit\pg_wrapper\tests;

use sad_spirit\pg_wrapper\Connection,
    sad_spirit\pg_wrapper\converters,
    sad_spirit\pg_wrapper\exceptions\InvalidArgumentException;

/**
 * Unit test for ResultSet class
 */
class ResultSetTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Connection
     */
    static $conn;

    static public function setUpBeforeClass()
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            return;
        }
        self::$conn = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);
        self::$conn->execute('drop table if exists test_resultset');
        self::$conn->execute('create table test_resultset (one integer, two text, three text)');
        self::$conn->execute(<<<SQL
insert into test_resultset values
    (1, 'foo', 'first value of foo'),
    (3, 'foo', 'second value of foo'),
    (5, 'bar', 'first value of bar'),
    (7, 'bar', 'second value of bar'),
    (9, 'baz', 'only value of baz')
SQL
);
    }

    public function setUp()
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
    }

    public function testSetMode()
    {
        $res = self::$conn->execute("select * from test_resultset where one = 1");
        $this->assertEquals(array('one' => 1, 'two' => 'foo', 'three' => 'first value of foo'), $res[0]);

        $res->setMode(PGSQL_NUM);
        $this->assertEquals(array(1, 'foo', 'first value of foo'), $res[0]);
    }

    public function testSetType()
    {
        $res = self::$conn->execute("select row(one, three) as onethree from test_resultset where two = 'baz'");
        $this->assertEquals('(9,"only value of baz")', $res[0]['onethree']);

        $res->setType('onethree', new converters\containers\CompositeConverter(
            array('a' => new converters\IntegerConverter(), 'b' => new converters\StringConverter())
        ));
        $this->assertEquals(array('a' => 9, 'b' => 'only value of baz'), $res[0]['onethree']);
    }

    public function testSetTypeMissingField()
    {
        $res = self::$conn->execute("select one, two from test_resultset");

        try {
            $res->setType('three', new converters\StubConverter());
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            try {
                $res->setType(3, new converters\StubConverter());
                $this->fail('Expected InvalidArgumentException was not thrown');
            } catch (InvalidArgumentException $e) {}
        }
    }

    public function testFetchColumn()
    {
        $res = self::$conn->execute("select one, two from test_resultset where one > 5");

        $this->assertEquals(array(7, 9), $res->fetchColumn(0));
        $this->assertEquals(array('bar', 'baz'), $res->fetchColumn('two'));
    }

    public function testFetchColumnMissingField()
    {
        $res = self::$conn->execute("select one, two from test_resultset");

        try {
            $res->fetchColumn('three');
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            try {
                $res->fetchColumn(3);
                $this->fail('Expected InvalidArgumentException was not thrown');
            } catch (InvalidArgumentException $e) {}
        }
    }

    public function testFetchAll()
    {
        $res = self::$conn->execute("select one, two from test_resultset where one > 5 order by one");

        $this->assertEquals(
            array(
                array('one' => 7, 'two' => 'bar'),
                array('one' => 9, 'two' => 'baz')
            ),
            $res->fetchAll(PGSQL_ASSOC)
        );

        $this->assertEquals(
            array(
                array(7, 'bar'),
                array(9, 'baz')
            ),
            $res->fetchAll(PGSQL_NUM)
        );
    }

    public function testFetchAllUsingKeyWithTwoColumns()
    {
        $res = self::$conn->execute("select one, two from test_resultset where one < 9 order by one");

        $this->assertEquals(
            array(
                'foo' => 3,
                'bar' => 7
            ),
            $res->fetchAll(null, 'two')
        );

        $this->assertEquals(
            array(
                1 => array('foo'),
                3 => array('foo'),
                5 => array('bar'),
                7 => array('bar')
            ),
            $res->fetchAll(PGSQL_NUM, 'one', true)
        );

        $this->assertEquals(
            array(
                'foo' => array(1, 3),
                'bar' => array(5, 7)
            ),
            $res->fetchAll(null, 1, false, true)
        );
    }

    public function testFetchAllUsingKey()
    {
        $res = self::$conn->execute("select * from test_resultset where one > 1 order by one");

        $this->assertEquals(
            array(
                3 => array('foo', 'second value of foo'),
                5 => array('bar', 'first value of bar'),
                7 => array('bar', 'second value of bar'),
                9 => array('baz', 'only value of baz')
            ),
            $res->fetchAll(PGSQL_NUM, 'one')
        );

        $this->assertEquals(
            array(
                'foo' => array(
                    array('one' => 3, 'three' => 'second value of foo')
                ),
                'bar' => array(
                    array('one' => 5, 'three' => 'first value of bar'),
                    array('one' => 7, 'three' => 'second value of bar')
                ),
                'baz' => array(
                    array('one' => 9, 'three' => 'only value of baz')
                )
            ),
            $res->fetchAll(PGSQL_ASSOC, 1, false, true)
        );
    }
}