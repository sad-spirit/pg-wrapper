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
 * @copyright 2014-2021 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

namespace sad_spirit\pg_wrapper\tests;

use PHPUnit\Framework\{
    TestCase,
    MockObject\MockObject
};
use sad_spirit\pg_wrapper\{
    Connection,
    exceptions\InvalidArgumentException,
    exceptions\RuntimeException,
    exceptions\TypeConversionException,
    TypeConverter,
    types\DateTimeMultiRange,
    types\DateTimeRange
};
use sad_spirit\pg_wrapper\converters\{
    datetime\DateConverter,
    DefaultTypeConverterFactory,
    FloatConverter,
    IntegerConverter,
    StringConverter,
    containers\ArrayConverter,
    containers\CompositeConverter,
    containers\HstoreConverter,
    containers\MultiRangeConverter,
    containers\RangeConverter,
    datetime\TimeConverter,
    datetime\TimeStampConverter,
    datetime\TimeStampTzConverter,
    geometric\PointConverter
};
use Psr\Cache\{
    CacheItemPoolInterface,
    CacheItemInterface
};

/**
 * Unit test for TypeConverterFactory class
 */
class DefaultTypeConverterFactoryTest extends TestCase
{
    /**
     * @var DefaultTypeConverterFactory
     */
    protected $factory;

    public function setUp(): void
    {
        $this->factory = new DefaultTypeConverterFactory();
    }

    /**
     * @dataProvider getBuiltinTypeConverters
     * @param string        $typeName
     * @param TypeConverter $converter
     */
    public function testGetConverterForBuiltInType(string $typeName, TypeConverter $converter): void
    {
        $this->assertEquals($converter, $this->factory->getConverterForTypeSpecification($typeName));
    }

    /**
     * @dataProvider getSqlStandardTypeConverters
     * @param string        $typeName
     * @param TypeConverter $converter
     */
    public function testGetConverterForSqlStandardType(string $typeName, TypeConverter $converter): void
    {
        $this->assertEquals($converter, $this->factory->getConverterForTypeSpecification($typeName));
    }

    /**
     * @dataProvider getBuiltinTypeConverters
     * @param string        $typeName
     * @param TypeConverter $converter
     */
    public function testGetConverterForBuiltinTypeArray(string $typeName, TypeConverter $converter): void
    {
        $this->assertEquals(
            new ArrayConverter($converter),
            $this->factory->getConverterForTypeSpecification($typeName . '[]')
        );
    }

    /**
     * @dataProvider getSqlStandardTypeConverters
     * @param string $typeName
     * @param TypeConverter $converter
     */
    public function testGetConverterForSqlStandardTypeArray(string $typeName, TypeConverter $converter): void
    {
        $this->assertEquals(
            new ArrayConverter($converter),
            $this->factory->getConverterForTypeSpecification($typeName . '[]')
        );
    }

    /**
     * @dataProvider getInvalidTypeNames
     * @param string $typeName
     * @param string $exceptionMessage
     */
    public function testInvalidTypeNames(string $typeName, string $exceptionMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($exceptionMessage);

        $this->factory->getConverterForTypeSpecification($typeName);
    }

    public function testGetConverterForCompositeTypeUsingArray(): void
    {
        $this->assertEquals(
            new CompositeConverter([
                'num'     => new IntegerConverter(),
                'string'  => new StringConverter(),
                'strings' => new ArrayConverter(new StringConverter()),
                'coord'   => new PointConverter()
            ]),
            $this->factory->getConverterForTypeSpecification([
                'num'     => 'integer',
                'string'  => '"varchar"',
                'strings' => 'text[]',
                'coord'   => 'pg_catalog.point'
            ])
        );
    }

    public function testMissingConverter(): void
    {
        $this::expectException(RuntimeException::class);
        $this::expectExceptionMessage('connection required');

        $this->factory->getConverterForTypeSpecification('foo');
    }

    public function testRequireQualifiedName(): void
    {
        $this->factory->registerConverter(IntegerConverter::class, 'foo', 'bar');
        $this->factory->registerConverter(TimeConverter::class, 'foo', 'baz');

        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('Qualified name required');

        $this->factory->getConverterForTypeSpecification('foo');
    }

    public function testMissingTypeWithDatabaseConnection(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist in the database');

        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING, false);
        $connection->setTypeConverterFactory($this->factory);
        $this->factory->getConverterForTypeSpecification('missing');
    }

    public function testMissingConverterWithDatabaseConnection(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no converter registered for base type');

        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING, false);
        $connection->setTypeConverterFactory($this->factory);
        $this->factory->getConverterForTypeSpecification('xml');
    }

    public function testBuiltinTypeOid(): void
    {
        $this::assertEquals(new IntegerConverter(), $this->factory->getConverterForTypeOID(23));
    }

    public function testCustomTypeOidRequiresConnection(): void
    {
        $this::expectException(RuntimeException::class);
        $this::expectExceptionMessage('Database connection required');

        $this->factory->getConverterForTypeOID(1000000);
    }

    public function testGetConverterByTypeOid(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this::markTestSkipped('Connection string is not configured');
        }
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING, false);
        $connection->setTypeConverterFactory($this->factory);

        $result = $connection->executeParams(
            'select t.oid from pg_type as t join pg_namespace as n on t.typnamespace = n.oid'
            . ' where t.typname = $2 and n.nspname = $1',
            ['information_schema', 'tables']
        );

        $this::assertInstanceOf(CompositeConverter::class, $this->factory->getConverterForTypeOID($result[0]['oid']));
    }

    public function testArrayTypeConverterFromMetadata(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING, false);
        $connection->setTypeConverterFactory($this->factory);
        $this->assertEquals(
            new ArrayConverter(new IntegerConverter()),
            $this->factory->getConverterForTypeSpecification('_int4')
        );
    }

    public function testCompositeTypeConverterFromMetadata(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING, false);
        $connection->setTypeConverterFactory($this->factory);
        // this one has few columns, let's hope none are added later
        $serverVersion = pg_parameter_status($connection->getResource(), 'server_version');
        $oidColumn     = version_compare($serverVersion, '12', '<')
                         ? []
                         : ['oid' => new IntegerConverter()];
        $this->assertEquals(
            new CompositeConverter($oidColumn + [
                'cfgname'      => new StringConverter(),
                'cfgnamespace' => new IntegerConverter(),
                'cfgowner'     => new IntegerConverter(),
                'cfgparser'    => new IntegerConverter()
            ]),
            $this->factory->getConverterForTypeSpecification('pg_ts_config')
        );
    }

    public function testRangeTypeConverterFromMetadata(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING, false);
        $connection->setTypeConverterFactory($this->factory);
        $connection->execute("drop type if exists textrange");
        $connection->execute("create type textrange as range (subtype=text, collation=\"C\")");

        $this->assertEquals(
            new RangeConverter(new StringConverter()),
            $this->factory->getConverterForTypeSpecification('textrange')
        );
    }

    public function testMultiRangeTypeConverterFromMetadata(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this::markTestSkipped('Connection string is not configured');
        }
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING, false);
        $connection->setTypeConverterFactory($this->factory);
        $serverVersion = pg_parameter_status($connection->getResource(), 'server_version');
        if (version_compare($serverVersion, '14-beta', '<')) {
            $this::markTestSkipped('Postgres version 14 is required for multirange support');
        }

        $connection->execute("drop type if exists textrange");
        $connection->execute(
            "create type textrange as range (subtype=text, collation=\"C\", multirange_type_name=textmultirange)"
        );

        $this::assertEquals(
            new MultiRangeConverter(new StringConverter()),
            $this->factory->getConverterForTypeSpecification('textmultirange')
        );
    }

    public function testEnumTypeConverterFromMetadata(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING, false);
        $connection->setTypeConverterFactory($this->factory);
        $connection->execute("drop type if exists testenum");
        $connection->execute("create type testenum as enum('yes', 'no', 'maybe', 'test')");

        $result = $connection->execute("select 'maybe'::testenum as value");
        $this->assertSame('maybe', $result[0]['value']);
    }

    public function testDomainConverterFromMetadata(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this::markTestSkipped('Connection string is not configured');
        }

        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING, false);
        $connection->setTypeConverterFactory($this->factory);
        $connection->execute("drop domain if exists testdomain");
        $connection->execute("create domain testdomain as text check (value in ('yes', 'no', 'maybe', 'test'))");

        $this::assertInstanceOf(StringConverter::class, $this->factory->getConverterForTypeSpecification('testdomain'));
    }

    public function testMetadataIsStoredInCache(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }

        /* @var $mockPool CacheItemPoolInterface|MockObject */
        $mockPool = $this->getMockBuilder(CacheItemPoolInterface::class)
            ->setMethods(['getItem', 'save'])
            ->getMockForAbstractClass();
        /* @var $mockItem CacheItemInterface|MockObject */
        $mockItem = $this->getMockBuilder(CacheItemInterface::class)
            ->setMethods(['isHit', 'set'])
            ->getMockForAbstractClass();

        $mockPool->expects($this->once())
            ->method('getItem')
            ->will($this->returnValue($mockItem));

        $mockPool->expects($this->once())
            ->method('save')
            ->with($mockItem);

        $mockItem->expects($this->once())
            ->method('isHit')
            ->will($this->returnValue(false));

        $mockItem->expects($this->once())
            ->method('set')
            ->withAnyParameters()
            ->willReturnSelf();

        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);
        $connection->setMetadataCache($mockPool)
            ->setTypeConverterFactory($this->factory);

        $this->assertEquals(
            new IntegerConverter(),
            $this->factory->getConverterForTypeSpecification('information_schema.cardinal_number')
        );
    }

    public function testMetadataIsLoadedFromCache(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }

        /* @var $mockPool CacheItemPoolInterface|MockObject */
        $mockPool = $this->getMockBuilder(CacheItemPoolInterface::class)
            ->setMethods(['getItem', 'save'])
            ->getMockForAbstractClass();
        /* @var $mockItem CacheItemInterface|MockObject */
        $mockItem = $this->getMockBuilder(CacheItemInterface::class)
            ->setMethods(['isHit', 'set'])
            ->getMockForAbstractClass();

        $mockPool->expects($this->once())
            ->method('getItem')
            ->will($this->returnValue($mockItem));

        $mockPool->expects($this->never())
            ->method('save');

        $mockItem->expects($this->once())
            ->method('isHit')
            ->will($this->returnValue(true));

        $mockItem->expects($this->once())
            ->method('get')
            ->will($this->returnValue([
                'composite' => [],
                'array'     => [],
                'range'     => [],
                'names'     => ['blah' => ['blah' => 123456]]
            ]));

        $mockItem->expects($this->never())
            ->method('set');

        $this->factory->registerConverter(HstoreConverter::class, 'blah', 'blah');
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);
        $connection->setMetadataCache($mockPool)
            ->setTypeConverterFactory($this->factory);

        $this->assertEquals(new HstoreConverter(), $this->factory->getConverterForTypeOID(123456));
    }

    public function testConfiguresTypeConverterArgumentUsingConnection(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING, false);
        $connection->setTypeConverterFactory($this->factory);

        $mockConverter = $this->createMock(TimeConverter::class);
        $mockConverter->expects($this->once())
            ->method('setConnectionResource');

        $this->assertSame($mockConverter, $this->factory->getConverterForTypeSpecification($mockConverter));
    }

    public function testConnectionAwareSubConverterOfArrayShouldBeConfigured(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this::markTestSkipped('Connection string is not configured');
        }

        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);
        $connection->setTypeConverterFactory($this->factory);

        $connection->execute("set datestyle='sql, mdy'");
        $result = $connection->execute(
            "select array['2019-04-26']::date[]",
            [new ArrayConverter(new DateConverter())]
        );

        $this::assertEquals(
            [[new \DateTime('2019-04-26')]],
            $result->fetchColumn(0)
        );
    }

    public function testConnectionAwareSubConverterOfRangeShouldBeConfigured(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this::markTestSkipped('Connection string is not configured');
        }

        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);
        $connection->setTypeConverterFactory($this->factory);

        $connection->execute("set datestyle='german'");
        $result = $connection->execute(
            "select daterange('2019-04-26', '2019-04-27', '[]')",
            ['daterange']
        );

        $this::assertEquals(
            [new DateTimeRange(new \DateTime('2019-04-26'), new \DateTime('2019-04-28'), true, false)],
            $result->fetchColumn(0)
        );
    }

    public function testConnectionAwareSubConverterOfMultiRangeShouldBeConfigured(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this::markTestSkipped('Connection string is not configured');
        }

        $connection    = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);
        $connection->setTypeConverterFactory($this->factory);
        $serverVersion = pg_parameter_status($connection->getResource(), 'server_version');
        if (version_compare($serverVersion, '14-beta', '<')) {
            $this::markTestSkipped('Postgres version 14 is required for multirange support');
        }

        $result = $connection->execute(
            "select datemultirange(daterange('2019-04-26', '2019-04-27', '[]'))",
            ['datemultirange']
        );

        $this->assertEquals(
            [new DateTimeMultiRange(
                new DateTimeRange(new \DateTime('2019-04-26'), new \DateTime('2019-04-28'), true, false)
            )],
            $result->fetchColumn(0)
        );
    }

    public function testConnectionAwareSubConverterOfCompositeTypeShouldBeConfigured(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this::markTestSkipped('Connection string is not configured');
        }

        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);
        $connection->setTypeConverterFactory($this->factory);

        $connection->execute("set datestyle='postgres'");
        $result = $connection->execute(
            "select row('2019-04-26'::date, 1::integer)",
            [new CompositeConverter(['foo' => new DateConverter(), 'bar' => new IntegerConverter()])]
        );

        $this::assertEquals(
            [['foo' => new \DateTime('2019-04-26'), 'bar' => 1]],
            $result->fetchColumn(0)
        );
    }

    public function testConvertingInstanceOfUnknownClassResultsInException(): void
    {
        $this::expectException(TypeConversionException::class);
        $this::expectExceptionMessage('Failed to deduce');

        $this->factory->getConverterForPHPValue((object)['foo' => 'bar']);
    }

    public function testCanRegisterAMappingFromPHPClassToDBType(): void
    {
        $this->factory->registerClassMapping(\stdClass::class, 'json');

        $value = (object)['foo' => 'bar'];
        $this::assertEquals(
            '{"foo":"bar"}',
            $this->factory->getConverterForPHPValue($value)->output($value)
        );
    }

    public function testDisallowSetConnectionWithDifferentConnectionInstance(): void
    {
        $connectionOne = new Connection('does this really matter?');
        $connectionTwo = new Connection('or this?');

        $connectionOne->setTypeConverterFactory($this->factory);
        $connectionOne->setTypeConverterFactory($this->factory);

        $this::expectException(RuntimeException::class);
        $this::expectExceptionMessage('already set');
        $connectionTwo->setTypeConverterFactory($this->factory);
    }

    public function testSetConnectionUpdatesExistingConverters(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this::markTestSkipped('Connection string is not configured');
        }

        $converter = $this->factory->getConverterForTypeSpecification('date');
        try {
            $converter->input('18.09.2020');
            $this::fail('Expected TypeConversionException was not thrown');
        } catch (TypeConversionException $e) {
        }

        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);
        $connection->setTypeConverterFactory($this->factory);

        $connection->execute("set datestyle='German'");

        $this::assertEquals('2020-09-18', $converter->input('18.09.2020')->format('Y-m-d'));
    }

    public function getBuiltinTypeConverters(): array
    {
        return [
            ['int4',                    new IntegerConverter()],
            ['pg_cAtalOg.iNt4',         new IntegerConverter()],
            ['"pg_catalog" . "int4" ',  new IntegerConverter()],
            ['hSTOre',                  new HstoreConverter()],
            ['pUblic.hstore',           new HstoreConverter()],
            [' "public" . "hstore"',    new HstoreConverter()],
            ['tsrange',                 new RangeConverter(new TimeStampConverter())],
            ['pg_catalog.int4range',    new RangeConverter(new IntegerConverter())],
            ['pg_catalog.tsmultirange', new MultiRangeConverter(new TimeStampConverter())]
        ];
    }

    public function getSqlStandardTypeConverters(): array
    {
        return [
            ['integer',                    new IntegerConverter()],
            ['DOUBLE   precision',         new FloatConverter()],
            ["timestamp  WITH\ntime zone", new TimeStampTzConverter()],
            ['time without time zone',     new TimeConverter()]
        ];
    }

    public function getInvalidTypeNames(): array
    {
        return [
            ['',             'Missing type name'],
            ['foo.',         'Missing type name'],
            ['foo bar',      'Unexpected identifier'],
            ['foo "bar"',    'Unexpected double-quoted string'],
            ['foo[bar]',     'Invalid array specification'],
            ['foo[',         'Invalid array specification'],
            ['[]',           'Invalid array specification'],
            ['.foo',         'Extra dots'],
            ['foo.bar.baz',  'Extra dots'],
            ['foo.""',       'Invalid double-quoted string'],
            ['foo."bar',     'Invalid double-quoted string'],
            ['foo(666)',     'Unexpected symbol']
        ];
    }
}
