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

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_wrapper\{
    Connection,
    exceptions\InvalidQueryException,
    exceptions\RuntimeException
};

/**
 * Unit test for transactions handling in Connection class
 */
class ConnectionTransactionsTest extends TestCase
{
    /**
     * @var Connection
     */
    protected static $conn;

    public static function setUpBeforeClass(): void
    {
        if (TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            self::$conn = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);
            self::$conn->execute('drop table if exists test_trans');
            self::$conn->execute('create table test_trans (id integer)');
        }
    }

    public function setUp(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        self::$conn->execute('truncate test_trans');
    }

    protected function assertPreConditions(): void
    {
        $this->assertFalse(self::$conn->inTransaction());
    }

    public function tearDown(): void
    {
        if (self::$conn && self::$conn->inTransaction()) {
            self::$conn->rollback();
        }
    }

    public function testBeginCommit()
    {
        self::$conn->beginTransaction();
        $this->assertTrue(self::$conn->inTransaction());
        self::$conn->beginTransaction();
        self::$conn->commit();
        $this->assertFalse(self::$conn->inTransaction());
    }

    public function testExplicitBeginCommitQueries()
    {
        self::$conn->execute('begin');
        $this->assertTrue(self::$conn->inTransaction());
        self::$conn->execute('commit');
        $this->assertFalse(self::$conn->inTransaction());
    }

    public function testBeginRollback()
    {
        self::$conn->beginTransaction();
        self::$conn->execute('insert into test_trans values (1)');
        $result = self::$conn->execute('select count(*) as cnt from test_trans');
        $this->assertEquals(1, $result[0]['cnt']);

        try {
            self::$conn->execute("insert into test_trans values ('foo')");
            $this->fail('Expected InvalidQueryException was not thrown');
        } catch (InvalidQueryException $e) {
        }
        $this->assertTrue(self::$conn->inTransaction());
        self::$conn->rollback();

        $this->assertFalse(self::$conn->inTransaction());
        $result = self::$conn->execute('select count(*) as cnt from test_trans');
        $this->assertEquals(0, $result[0]['cnt']);
    }

    public function testDisallowSavepointOutsideTransaction()
    {
        $this->expectException(RuntimeException::class);
        self::$conn->beginTransaction('foo');
    }

    public function testSavepoints()
    {
        self::$conn->beginTransaction();
        self::$conn->execute('insert into test_trans values (1)');
        self::$conn->beginTransaction('first');
        self::$conn->execute('insert into test_trans values (2)');
        self::$conn->beginTransaction('second');

        self::$conn->commit('second');
        try {
            self::$conn->commit('third');
            $this->fail('Expected InvalidQueryException was not thrown');
        } catch (InvalidQueryException $e) {
        }
        self::$conn->rollback('first');
        self::$conn->commit();
        $this->assertFalse(self::$conn->inTransaction());

        $result = self::$conn->execute('select array_agg(id) as ids from test_trans');
        $this->assertEquals([1], $result[0]['ids']);
    }
}
