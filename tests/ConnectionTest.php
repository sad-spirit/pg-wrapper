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
    exceptions\ConnectionException,
    exceptions\TypeConversionException,
    ResultSet,
    types\Box,
    types\Circle,
    types\DateTimeRange,
    types\Line,
    types\LineSegment,
    types\NumericRange,
    types\Path,
    types\Point,
    types\Polygon,
    types\Tid
};
use sad_spirit\pg_wrapper\converters\datetime\TimeStampTzConverter;

/**
 * Unit test for Connection class
 */
class ConnectionTest extends TestCase
{
    public function testDefaultConnectionIsLazy()
    {
        $connection = new Connection('this will probably fail');
        $this->assertFalse($connection->isConnected());
    }

    public function testInvalidConnectionString()
    {
        $this->expectException(ConnectionException::class);
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
            $this->assertStringContainsString('invalid connection option', $e->getMessage());
        }
        restore_error_handler();
    }

    public function testCanConnect()
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);
        $this->assertIsResource($connection->getResource());
        $this->assertStringContainsString('pgsql link', get_resource_type($connection->getResource()));
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

    public function testClonedInstanceIsDisconnected()
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING, false);
        $cloned     = clone $connection;

        $this->assertTrue($connection->isConnected());
        $this->assertFalse($cloned->isConnected());
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
     */
    public function testFailToQuoteImplicitTypes($value)
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $this->expectException(TypeConversionException::class);
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

        $mockTimestamp = $this->getMockBuilder(TimeStampTzConverter::class)
            ->getMock();

        $mockTimestamp->expects($this->once())
            ->method('setConnectionResource');
        $mockTimestamp->expects($this->once())
            ->method('output')
            ->willReturnArgument(0);

        $connection->executeParams(
            'select * from pg_stat_activity where query_start < $1',
            ['yesterday'],
            [$mockTimestamp]
        );
    }

    public function testConnectionExceptionIsThrownIfConnectionBroken()
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }

        $connectionOne = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING, false);
        $connectionTwo = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING, false);

        $this::assertInstanceOf(ResultSet::class, $connectionOne->execute('select true'));

        $connectionTwo->executeParams(
            'select pg_terminate_backend($1)',
            [pg_get_pid($connectionOne->getResource())]
        );

        $this::expectException(ConnectionException::class);
        $connectionOne->execute('select true');
    }

    public function getImplicitTypes()
    {
        return [
            [null,                         'NULL'],
            [false,                        "'f'"],
            [1,                            "'1'"],
            [2.3,                          "'2.3'"],
            ['foo',                        "'foo'"],
            ["o'bar",                      "'o''bar'"],
            [new \DateTime('2001-02-03'),  "'2001-02-03 00:00:00.000000+0000'"],
            [new \DateInterval('P1YT1S'),  "'P1YT1S'"],

            [new Box(new Point(1, 2), new Point(3, 4)), "'((1,2),(3,4))'"],
            [new Circle(new Point(1, 2), 3),            "'<(1,2),3>'"],
            [new Line(1.2, 3.4, 5.6),                   "'{1.2,3.4,5.6}'"],
            [
                new LineSegment(new Point(1, 2), new Point(3, 4)),
                "'[(1,2),(3,4)]'"
            ],
            [
                new Path([new Point(1, 2), new Point(3, 4), new Point(5, 6)], true),
                "'[(1,2),(3,4),(5,6)]'"
            ],
            [new Point(5.6, 7.8), "'(5.6,7.8)'"],
            [
                new Polygon([new Point(0, 0), new Point(0, 1), new Point(1, 0)]),
                "'((0,0),(0,1),(1,0))'"
            ],

            [
                new DateTimeRange(new \DateTime('2014-01-01'), new \DateTime('2014-12-31')),
                "'[\"2014-01-01 00:00:00.000000+0000\",\"2014-12-31 00:00:00.000000+0000\")'"
            ],
            [new NumericRange(1.2, 3.4, false, true), "'(\"1.2\",\"3.4\"]'"],

            [new Tid(200, 100),                       "'(200,100)'"]
        ];
    }

    public function getImplicitTypesFail()
    {
        return [
            [[]],
            [new \stdClass()]
        ];
    }

    public function getExplicitTypes()
    {
        return [
            [['foo' => 'bar'],    'hstore',   '\'"foo"=>"bar"\''],
            [[1, 2],              '_int4',    '\'{"1","2"}\''],
            [[1, 2],              'point',    "'(1,2)'"]
        ];
    }
}
