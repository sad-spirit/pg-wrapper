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
 * @copyright 2014-2020 Alexey Borzov
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
    server\InternalErrorException,
    server\OperationalException,
    server\ProgrammingException,
    server\QueryCanceledException,
    server\TransactionRollbackException
};

class ServerExceptionsTest extends TestCase
{
    /**
     * @var Connection
     */
    protected $conn;

    public function setUp(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this::markTestSkipped('Connection string is not configured');
        }
        $this->conn = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);
    }

    public function testConnectionExceptionIsThrownIfConnectionBroken()
    {
        $connectionTwo = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING, false);

        $this::assertInstanceOf(ResultSet::class, $connectionTwo->execute('select true'));

        $this->conn->executeParams(
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
        $this->conn->execute("select xmlvalidate('<foo />', 'bar')");
    }

    public function testDataException()
    {
        $this::expectException(DataException::class);
        $this->conn->execute('select 0/0');
    }

    public function testConstraintViolationException()
    {
        $this->conn->execute('drop table if exists test_exception');
        $this->conn->execute('create table test_exception (id integer not null, constraint test_exception_pkey primary key (id))');
        $this->conn->execute('insert into test_exception values (1)');

        try {
            $this->conn->execute('insert into test_exception values (null)');
            $this::fail('Expected ConstraintViolationException was not thrown');
        } catch (ConstraintViolationException $e) {
            $this::assertEquals(ServerException::NOT_NULL_VIOLATION, $e->getSqlState());
            $this::assertEquals(null, $e->getConstraintName());
        }

        try {
            $this->conn->execute('insert into test_exception values (1)');
            $this::fail('Expected ConstraintViolationException was not thrown');
        } catch (ConstraintViolationException $e) {
            $this::assertEquals(ServerException::UNIQUE_VIOLATION, $e->getSqlState());
            $this::assertEquals('test_exception_pkey', $e->getConstraintName());
        }
    }

    public function testInsufficientPrivilegeException()
    {
        $this::expectException(InsufficientPrivilegeException::class);
        $this->conn->execute('drop table pg_class');
    }

    public function testProgrammingException()
    {
        $this::expectException(ProgrammingException::class);
        $this->conn->execute('blah');
    }

    public function testInternalErrorException()
    {
        $this::expectException(InternalErrorException::class);

        $this->conn->beginTransaction();
        try {
            $this->conn->execute('blah');
        } catch (ServerException $e) {
        }
        $this->conn->execute('select true');
    }

    public function testOperationalException()
    {
        $this::expectException(OperationalException::class);

        $result = $this->conn->execute('select current_database()');
        $result->setMode(PGSQL_NUM);
        $dbName = $result[0][0];

        $this->conn->execute(
            "drop database " . $this->conn->quoteIdentifier($dbName)
        );
    }

    public function testQueryCanceledException()
    {
        $this::expectException(QueryCanceledException::class);
        $this->conn->beginTransaction();
        $this->conn->execute('set local statement_timeout = 500');
        $this->conn->execute('select pg_sleep(1)');
    }

    public function testTransactionRollbackException()
    {
        $this::expectException(TransactionRollbackException::class);

        $this->conn->execute("drop table if exists test_deadlock");
        $this->conn->execute(<<<SQL
create table test_deadlock (
    id integer not null,
    txt text,
    constraint test_deadlock_pkey primary key (id)
)
SQL
        );

        $this->conn->execute("insert into test_deadlock values (1, 'foo')");
        $this->conn->execute("insert into test_deadlock values (2, 'bar')");

        $connectionTwo = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING, false);

        $this->conn->beginTransaction();
        $connectionTwo->beginTransaction();
        // ensure that deadlock is detected in "synchronous" transaction
        $this->conn->execute("set deadlock_timeout = '1s'");
        $connectionTwo->execute("set deadlock_timeout = '10s'");

        $this->conn->execute("update test_deadlock set txt = 'baz' where id = 1");
        $connectionTwo->execute("update test_deadlock set txt = 'quux' where id = 2");

        // this will wait for lock, so we execute it asynchronously
        pg_send_query(
            $connectionTwo->getResource(),
            "update test_deadlock set txt = 'quux' where id = 1"
        );

        // this will wait for lock as well and trigger deadlock detection in 1s
        $this->conn->execute("update test_deadlock set txt = 'baz' where id = 2");

        $this->conn->commit();
    }
}