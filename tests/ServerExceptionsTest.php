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
 * @copyright 2014-2019 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

namespace sad_spirit\pg_wrapper\tests;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_wrapper\{
    Connection,
    ResultSet
};
use sad_spirit\pg_wrapper\exceptions\{
    ConnectionException,
    ServerException,
    server\ConstraintViolationException,
    server\DataException,
    server\FeatureNotSupportedException,
    server\InsufficientPrivilegeException,
    server\ProgrammingException
};

class ServerExceptionsTest extends TestCase
{
    /**
     * @var Connection
     */
    protected static $conn;

    public static function setUpBeforeClass(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            self::markTestSkipped('Connection string is not configured');

        } else {
            self::$conn = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);
            self::$conn->execute('drop table if exists test_exception');
            self::$conn->execute('create table test_exception (id integer not null, constraint test_exception_pkey primary key (id))');
        }
    }

    public function setUp(): void
    {
        self::$conn->execute('truncate test_exception');
    }

    public function testConnectionExceptionIsThrownIfConnectionBroken()
    {
        $connectionTwo = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING, false);

        $this::assertInstanceOf(ResultSet::class, $connectionTwo->execute('select true'));

        self::$conn->executeParams(
            'select pg_terminate_backend($1)',
            [pg_get_pid($connectionTwo->getResource())]
        );

        $this::expectException(ConnectionException::class);
        $connectionTwo->execute('select true');
    }

    public function testFeatureNotSupportedException()
    {
        $this::expectException(FeatureNotSupportedException::class);
        // This is the easiest way to trigger, see src\backend\utils\adt\xml.c
        self::$conn->execute("select xmlvalidate('<foo />', 'bar')");
    }

    public function testDataException()
    {
        $this::expectException(DataException::class);
        self::$conn->execute('select 0/0');
    }

    public function testConstraintViolationException()
    {
        self::$conn->execute('insert into test_exception values (1)');

        try {
            self::$conn->execute('insert into test_exception values (null)');
            $this::fail('Expected ConstraintViolationException was not thrown');
        } catch (ConstraintViolationException $e) {
            $this::assertEquals(ServerException::NOT_NULL_VIOLATION, $e->getSqlState());
            $this::assertEquals(null, $e->getConstraintName());
        }

        try {
            self::$conn->execute('insert into test_exception values (1)');
            $this::fail('Expected ConstraintViolationException was not thrown');
        } catch (ConstraintViolationException $e) {
            $this::assertEquals(ServerException::UNIQUE_VIOLATION, $e->getSqlState());
            $this::assertEquals('test_exception_pkey', $e->getConstraintName());
        }
    }

    public function testInsufficientPrivilegeException()
    {
        $this::expectException(InsufficientPrivilegeException::class);
        self::$conn->execute('drop table pg_class');
    }

    public function testProgrammingException()
    {
        $this::expectException(ProgrammingException::class);
        self::$conn->execute('blah');
    }
}