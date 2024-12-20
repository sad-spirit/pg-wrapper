<?php

/*
 * This file is part of sad_spirit/pg_wrapper:
 * converter of complex PostgreSQL types and an OO wrapper for PHP's pgsql extension.
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace sad_spirit\pg_wrapper\tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PgSql\Connection as NativeConnection;
use sad_spirit\pg_wrapper\{
    Connection,
    converters\StubTypeConverterFactory,
    exceptions\ConnectionException,
    exceptions\TypeConversionException,
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
    public function testDefaultConnectionIsLazy(): void
    {
        $connection = new Connection('this will probably fail');
        $this->assertFalse($connection->isConnected());
    }

    public function testInvalidConnectionString(): void
    {
        $this->expectException(ConnectionException::class);
        $this::expectExceptionMessage('invalid connection option');

        new Connection('blah=blah duh=oh', false);
    }

    public function testCanConnect(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);
        $this->assertInstanceOf(NativeConnection::class, $connection->getNative());
    }

    public function testDestructorDisconnects(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);
        $native     = $connection->getNative();

        $this::expectException(\Error::class);
        $this::expectExceptionMessage('already been closed');

        unset($connection);
        \pg_connection_status($native);
    }

    public function testNoReconnectAfterManualDisconnect(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this::markTestSkipped('Connection string is not configured');
        }
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING, false);
        $this::assertTrue($connection->isConnected());

        $connection->disconnect();

        $this::expectException(ConnectionException::class);
        $this::expectExceptionMessage("Connection has been closed");
        $connection->execute("select true");
    }

    public function testDifferentInstancesHaveDifferentNativeConnections(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $conn1 = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);
        $conn2 = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);
        $this->assertNotSame($conn1->getNative(), $conn2->getNative());
    }

    public function testClonedInstanceIsDisconnected(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING, false);
        $cloned     = clone $connection;

        $this->assertTrue($connection->isConnected());
        $this->assertFalse($cloned->isConnected());
    }

    #[DataProvider('getImplicitTypes')]
    public function testQuoteImplicitTypes(mixed $value, string $expected): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);
        $this->assertSame($expected, $connection->quote($value));
    }

    #[DataProvider('getImplicitTypesFail')]
    public function testFailToQuoteImplicitTypes(mixed $value): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $this->expectException(TypeConversionException::class);
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);
        $connection->quote($value);
    }

    #[DataProvider('getExplicitTypes')]
    public function testQuoteExplicitTypes(mixed $value, string $type, string $expected): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);
        $this->assertSame($expected, $connection->quote($value, $type));
    }

    public function testExecuteParamsConfiguresTypeConverterArgumentUsingConnection(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }

        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);

        $mockTimestamp = $this->createMock(TimeStampTzConverter::class);

        $mockTimestamp->expects($this->once())
            ->method('setConnection');
        $mockTimestamp->expects($this->once())
            ->method('output')
            ->willReturnArgument(0);

        $connection->executeParams(
            'select * from pg_stat_activity where query_start < $1',
            ['yesterday'],
            [$mockTimestamp]
        );
    }

    public function testClonedInstanceHasDifferentTypeConverterFactory(): void
    {
        $factory    = new StubTypeConverterFactory();
        $connection = new Connection('does this really matter?');
        $connection->setTypeConverterFactory($factory);
        $cloned     = clone $connection;

        $this::assertNotSame($connection->getTypeConverterFactory(), $cloned->getTypeConverterFactory());
    }

    public static function getImplicitTypes(): array
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
                new Path(true, new Point(1, 2), new Point(3, 4), new Point(5, 6)),
                "'[(1,2),(3,4),(5,6)]'"
            ],
            [new Point(5.6, 7.8), "'(5.6,7.8)'"],
            [
                new Polygon(new Point(0, 0), new Point(0, 1), new Point(1, 0)),
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

    public static function getImplicitTypesFail(): array
    {
        return [
            [[]],
            [new \stdClass()]
        ];
    }

    public static function getExplicitTypes(): array
    {
        return [
            [['foo' => 'bar'],    'hstore',   '\'"foo"=>"bar"\''],
            [[1, 2],              '_int4',    '\'{"1","2"}\''],
            [[1, 2],              'point',    "'(1,2)'"]
        ];
    }
}
