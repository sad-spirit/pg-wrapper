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

use sad_spirit\pg_wrapper\Connection;

/**
 * Unit test for PreparedStatement class
 */
class PreparedStatementTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Connection
     */
    protected $conn;

    public function setUp()
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $this->conn = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);
    }

    public function testCreatesPreparedStatement()
    {
        $statement = $this->conn->prepare('select * from pg_stat_activity where query_start < $1');

        $result = $this->conn->execute('select * from pg_prepared_statements where not from_sql');

        $this->assertEquals(1, count($result));
        $this->assertAttributeEquals($result[0]['name'], '_queryId', $statement);
    }

    public function testClonedStatementIsRePrepared()
    {
        $statement = $this->conn->prepare('select * from pg_stat_activity where query_start < $1');
        $cloned    = clone $statement;

        $result = $this->conn->execute('select count(*) as cnt from pg_prepared_statements where not from_sql');
        $this->assertEquals(2, $result[0]['cnt']);
    }

    public function testCanDeallocateStatement()
    {
        $statement = $this->conn->prepare('select * from pg_stat_activity where query_start < $1');

        $statement->deallocate();

        $result = $this->conn->execute('select * from pg_prepared_statements where not from_sql');
        $this->assertEquals(0, count($result));
    }

    /**
     * @expectedException \sad_spirit\pg_wrapper\exceptions\RuntimeException
     */
    public function testCannotExecuteAfterDeallocate()
    {
        $statement = $this->conn->prepare('select * from pg_stat_activity where query_start < $1');
        $statement->deallocate();

        $statement->execute(['yesterday']);
    }

    public function testBindParam()
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

    public function testBindValue()
    {
        $statement = $this->conn->prepare('select typname from pg_type where oid = $1');

        $statement->bindValue(1, 23);
        $result = $statement->execute();
        $this->assertEquals('int4', $result[0]['typname']);

        $result = $statement->execute([16]);
        $this->assertEquals('bool', $result[0]['typname']);
    }

    private function _createMockTimestampConverter()
    {
        $mockTimestamp = $this->getMockBuilder('\sad_spirit\pg_wrapper\converters\datetime\TimeStampTzConverter')
            ->getMock();

        $mockTimestamp->expects($this->once())
            ->method('setConnectionResource');
        $mockTimestamp->expects($this->once())
            ->method('output')
            ->willReturnArgument(0);

        return $mockTimestamp;
    }

    public function testConstructorConfiguresTypeConverterArgumentUsingConnection()
    {
        $statement = $this->conn->prepare(
            'select * from pg_stat_activity where query_start < $1',
            [$this->_createMockTimestampConverter()]
        );
        $statement->execute(['yesterday']);
    }

    public function testBindValueConfiguresTypeConverterArgumentUsingConnection()
    {
        $statement = $this->conn->prepare('select * from pg_stat_activity where query_start < $1');
        $statement->bindValue(1, 'yesterday', $this->_createMockTimestampConverter());
        $statement->execute();
    }

    public function testBindParamConfiguresTypeConverterArgumentUsingConnection()
    {
        $statement = $this->conn->prepare('select * from pg_stat_activity where query_start < $1');
        $statement->bindParam(1, $param, $this->_createMockTimestampConverter());

        $param = 'yesterday';
        $statement->execute();
    }
}