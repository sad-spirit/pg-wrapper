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
    sad_spirit\pg_wrapper\exceptions\ConnectionException,
    sad_spirit\pg_wrapper\types\Box,
    sad_spirit\pg_wrapper\types\Circle,
    sad_spirit\pg_wrapper\types\DateTimeRange,
    sad_spirit\pg_wrapper\types\Line,
    sad_spirit\pg_wrapper\types\LineSegment,
    sad_spirit\pg_wrapper\types\NumericRange,
    sad_spirit\pg_wrapper\types\Path,
    sad_spirit\pg_wrapper\types\Point,
    sad_spirit\pg_wrapper\types\Polygon,
    sad_spirit\pg_wrapper\types\Tid;

/**
 * Unit test for Connection class
 */
class ConnectionTest extends \PHPUnit_Framework_TestCase
{
    public function testDefaultConnectionIsLazy()
    {
        $connection = new Connection('this will probably fail');
        $this->assertFalse($connection->isConnected());
    }

    /**
     * @expectedException \sad_spirit\pg_wrapper\exceptions\ConnectionException
     */
    public function testInvalidConnectionString()
    {
        $connection = new Connection('blah=blah duh=oh', false);
    }

    public function testUndefinedPhpErrorMsg()
    {
        set_error_handler(function ($errno, $errstr) {
            return true;
        }, E_WARNING);
        try {
            $connection = new Connection('blah=blah duh=oh', false);
        } catch (ConnectionException $e) {
            $this->assertContains('invalid connection option', $e->getMessage());
        }
        restore_error_handler();
    }

    public function testCanConnect()
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);
        $this->assertInternalType('resource', $connection->getResource());
        $this->assertContains('pgsql link', get_resource_type($connection->getResource()));
    }

    public function testDestructorDisconnects()
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);
        $resource   = $connection->getResource();
        unset($connection);
        $this->assertEquals('Unknown', get_resource_type($resource));
    }

    public function testDifferentInstancesHaveDifferentResources()
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $conn1 = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);
        $conn2 = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);
        $this->assertNotSame($conn1->getResource(), $conn2->getResource());
    }

    /**
     * @expectedException \sad_spirit\pg_wrapper\exceptions\InvalidArgumentException
     */
    public function testCloneIsProhibited()
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);
        $cloned     = clone $connection;
    }

    /**
     * @dataProvider getImplicitTypes
     */
    public function testQuoteImplicitTypes($value, $expected)
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);
        $this->assertSame($expected, $connection->quote($value));
    }

    /**
     * @dataProvider getImplicitTypesFail
     * @expectedException \sad_spirit\pg_wrapper\exceptions\TypeConversionException
     */
    public function testFailToQuoteImplicitTypes($value)
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);
        $connection->quote($value);
    }

    /**
     * @dataProvider getExplicitTypes
     */
    public function testQuoteExplicitTypes($value, $type, $expected)
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);
        $this->assertSame($expected, $connection->quote($value, $type));
    }

    public function testExecuteParamsConfiguresTypeConverterArgumentUsingConnection()
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }

        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);

        $mockTimestamp = $this->getMock('\sad_spirit\pg_wrapper\converters\datetime\TimeStampTzConverter');

        $mockTimestamp->expects($this->once())
            ->method('setConnectionResource');
        $mockTimestamp->expects($this->once())
            ->method('output')
            ->willReturnArgument(0);

        $connection->executeParams(
            'select * from pg_stat_activity where query_start < $1',
            array('yesterday'),
            array($mockTimestamp)
        );
    }

    public function getImplicitTypes()
    {
        return array(
            array(null,                         'NULL'),
            array(false,                        "'f'"),
            array(1,                            "'1'"),
            array(2.3,                          "'2.3'"),
            array('foo',                        "'foo'"),
            array("o'bar",                      "'o''bar'"),
            array(new \DateTime('2001-02-03'),  "'2001-02-03 00:00:00.000000+0000'"),
            array(new \DateInterval('P1YT1S'),  "'P1YT1S'"),

            array(new Box(new Point(1, 2), new Point(3, 4)), "'((1,2),(3,4))'"),
            array(new Circle(new Point(1, 2), 3),            "'<(1,2),3>'"),
            array(new Line(1.2, 3.4, 5.6),                   "'{1.2,3.4,5.6}'"),
            array(
                new LineSegment(new Point(1, 2), new Point(3, 4)),
                "'[(1,2),(3,4)]'"
            ),
            array(
                new Path(array(new Point(1, 2), new Point(3, 4), new Point(5, 6)), true),
                "'[(1,2),(3,4),(5,6)]'"
            ),
            array(new Point(5.6, 7.8), "'(5.6,7.8)'"),
            array(
                new Polygon(array(new Point(0, 0), new Point(0, 1), new Point(1, 0))),
                "'((0,0),(0,1),(1,0))'"
            ),

            array(
                new DateTimeRange(new \DateTime('2014-01-01'), new \DateTime('2014-12-31')),
                "'[\"2014-01-01 00:00:00.000000+0000\",\"2014-12-31 00:00:00.000000+0000\")'"
            ),
            array(new NumericRange(1.2, 3.4, false, true), "'(\"1.2\",\"3.4\"]'"),

            array(new Tid(200, 100),                       "'(200,100)'")
        );
    }

    public function getImplicitTypesFail()
    {
        return array(
            array(array()),
            array(new \stdClass())
        );
    }

    public function getExplicitTypes()
    {
        return array(
            array(array('foo' => 'bar'),    'hstore',   '\'"foo"=>"bar"\''),
            array(array(1, 2),              '_int4',    '\'{"1","2"}\''),
            array(array(1, 2),              'point',    "'(1,2)'")
        );
    }
}