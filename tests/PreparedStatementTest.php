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
 * @copyright 2014-2023 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

namespace sad_spirit\pg_wrapper\tests;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_wrapper\{
    Connection,
    exceptions\RuntimeException
};
use sad_spirit\pg_wrapper\converters\datetime\TimeStampTzConverter;

/**
 * Unit test for PreparedStatement class
 */
class PreparedStatementTest extends TestCase
{
    /**
     * @var Connection
     */
    protected $conn;

    public function setUp(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $this->conn = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);
    }

    public function testCreatesPreparedStatement(): void
    {
        $statement = $this->conn->prepare('select * from pg_stat_activity where query_start < $1');

        $result = $this->conn->execute('select * from pg_prepared_statements where not from_sql');

        $this->assertEquals(1, count($result));
    }

    public function testClonedStatementIsRePrepared(): void
    {
        $statement = $this->conn->prepare('select * from pg_stat_activity where query_start < $1');
        $cloned    = clone $statement;

        $result = $this->conn->execute('select count(*) as cnt from pg_prepared_statements where not from_sql');
        $this->assertEquals(2, $result[0]['cnt']);
    }

    public function testCanDeallocateStatement(): void
    {
        $statement = $this->conn->prepare('select * from pg_stat_activity where query_start < $1');

        $statement->deallocate();

        $result = $this->conn->execute('select * from pg_prepared_statements where not from_sql');
        $this->assertEquals(0, count($result));
    }

    public function testCannotExecuteAfterDeallocate(): void
    {
        $this->expectException(RuntimeException::class);

        $statement = $this->conn->prepare('select * from pg_stat_activity where query_start < $1');
        $statement->deallocate();

        $statement->execute(['yesterday']);
    }

    public function testBindParam(): void
    {
        $statement = $this->conn->prepare('select typname from pg_type where oid = $1');

        $statement->bindParam(1, $param);
        $param = 23;
        $result = $statement->execute();
        $this->assertEquals('int4', $result[0]['typname']);

        $param = 16;
        $result = $statement->execute();
        $this->assertEquals('bool', $result[0]['typname']);

        $result = $statement->execute([18]);
        $this->assertEquals('char', $result[0]['typname']);
    }

    public function testBindValue(): void
    {
        $statement = $this->conn->prepare('select typname from pg_type where oid = $1');

        $statement->bindValue(1, 23);
        $result = $statement->execute();
        $this->assertEquals('int4', $result[0]['typname']);

        $result = $statement->execute([16]);
        $this->assertEquals('bool', $result[0]['typname']);
    }

    private function createMockTimestampConverter(): TimeStampTzConverter
    {
        $mockTimestamp = $this->createMock(TimeStampTzConverter::class);

        $mockTimestamp->expects($this->once())
            ->method('setConnection');
        $mockTimestamp->expects($this->once())
            ->method('output')
            ->willReturnArgument(0);

        return $mockTimestamp;
    }

    public function testConstructorConfiguresTypeConverterArgumentUsingConnection(): void
    {
        $statement = $this->conn->prepare(
            'select * from pg_stat_activity where query_start < $1',
            [$this->createMockTimestampConverter()]
        );
        $statement->execute(['yesterday']);
    }

    public function testBindValueConfiguresTypeConverterArgumentUsingConnection(): void
    {
        $statement = $this->conn->prepare('select * from pg_stat_activity where query_start < $1');
        $statement->bindValue(1, 'yesterday', $this->createMockTimestampConverter());
        $statement->execute();
    }

    public function testBindParamConfiguresTypeConverterArgumentUsingConnection(): void
    {
        $statement = $this->conn->prepare('select * from pg_stat_activity where query_start < $1');
        $statement->bindParam(1, $param, $this->createMockTimestampConverter());

        $param = 'yesterday';
        $statement->execute();
    }
}
