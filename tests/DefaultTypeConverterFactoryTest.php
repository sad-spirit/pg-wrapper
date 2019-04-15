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

use sad_spirit\pg_wrapper\{
    Connection,
    exceptions\InvalidArgumentException
};
use sad_spirit\pg_wrapper\converters\{
    DefaultTypeConverterFactory,
    FloatConverter,
    IntegerConverter,
    StringConverter,
    containers\ArrayConverter,
    containers\CompositeConverter,
    containers\HstoreConverter,
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
class DefaultTypeConverterFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var DefaultTypeConverterFactory
     */
    protected $factory;

    public function setUp()
    {
        $this->factory = new DefaultTypeConverterFactory();
    }

    /**
     * @dataProvider getBuiltinTypeConverters
     */
    public function testGetConverterForBuiltInType($typeName, $converter)
    {
        $this->assertEquals($converter, $this->factory->getConverter($typeName));
    }

    /**
     * @dataProvider getSqlStandardTypeConverters
     */
    public function testGetConverterForSqlStandardType($typeName, $converter)
    {
        $this->assertEquals($converter, $this->factory->getConverter($typeName));
    }

    /**
     * @dataProvider getBuiltinTypeConverters
     */
    public function testGetConverterForBuiltinTypeArray($typeName, $converter)
    {
        $this->assertEquals(
            new ArrayConverter($converter),
            $this->factory->getConverter($typeName . '[]')
        );
    }

    /**
     * @dataProvider getSqlStandardTypeConverters
     */
    public function testGetConverterForSqlStandardTypeArray($typeName, $converter)
    {
        $this->assertEquals(
            new ArrayConverter($converter),
            $this->factory->getConverter($typeName . '[]')
        );
    }

    /**
     * @dataProvider getInvalidTypeNames
     */
    public function testInvalidTypeNames($typeName, $exceptionMessage)
    {
        $this->setExpectedException(
            '\sad_spirit\pg_wrapper\exceptions\InvalidArgumentException',
            $exceptionMessage
        );
        $this->factory->getConverter($typeName);
    }

    public function testGetConverterForCompositeTypeUsingArray()
    {
        $this->assertEquals(new CompositeConverter([
                'num'     => new IntegerConverter(),
                'string'  => new StringConverter(),
                'strings' => new ArrayConverter(new StringConverter()),
                'coord'   => new PointConverter()
            ]),
            $this->factory->getConverter([
                'num'     => 'integer',
                'string'  => '"varchar"',
                'strings' => 'text[]',
                'coord'   => 'pg_catalog.point'
            ])
        );
    }

    public function testMissingConverter()
    {
        try {
            $this->factory->getConverter('foo');
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertContains('connection required', $e->getMessage());
        }

        $this->factory->registerConverter('\sad_spirit\pg_wrapper\converters\IntegerConverter', 'foo');
        $this->assertInstanceOf(
            '\sad_spirit\pg_wrapper\converters\IntegerConverter', $this->factory->getConverter('foo')
        );
    }

    public function testRequireQualifiedName()
    {
        $this->factory->registerConverter('\sad_spirit\pg_wrapper\converters\IntegerConverter', 'foo', 'bar');
        $this->factory->registerConverter('\sad_spirit\pg_wrapper\converters\datetime\TimeConverter', 'foo', 'baz');

        try {
            $this->factory->getConverter('foo');
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertContains('Qualified name required', $e->getMessage());
        }

        $this->assertInstanceOf(
            '\sad_spirit\pg_wrapper\converters\datetime\TimeConverter', $this->factory->getConverter('baz.foo')
        );
    }

    /**
     * @expectedException \sad_spirit\pg_wrapper\exceptions\InvalidArgumentException
     * @expectedExceptionMessage does not exist in the database
     */
    public function testMissingTypeWithDatabaseConnection()
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING, false);
        $connection->setTypeConverterFactory($this->factory);
        $this->factory->getConverter('missing');
    }

    /**
     * @expectedException \sad_spirit\pg_wrapper\exceptions\InvalidArgumentException
     * @expectedExceptionMessage no converter registered for base type
     */
    public function testMissingConverterWithDatabaseConnection()
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING, false);
        $connection->setTypeConverterFactory($this->factory);
        $this->factory->getConverter('trigger');
    }

    /**
     * @expectedException \sad_spirit\pg_wrapper\exceptions\InvalidArgumentException
     * @expectedExceptionMessage Database connection required
     */
    public function testTypeOidRequiresConnection()
    {
        $this->factory->getConverter(23);
    }

    public function testGetConverterByTypeOid()
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING, false);
        $connection->setTypeConverterFactory($this->factory);
        $this->assertEquals(new IntegerConverter(), $this->factory->getConverter(23));
    }

    public function testArrayTypeConverterFromMetadata()
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING, false);
        $connection->setTypeConverterFactory($this->factory);
        $this->assertEquals(
            new ArrayConverter(new IntegerConverter()),
            $this->factory->getConverter('_int4')
        );
    }

    public function testCompositeTypeConverterFromMetadata()
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING, false);
        $connection->setTypeConverterFactory($this->factory);
        // this one has few columns, let's hope none are added later
        $this->assertEquals(
            new CompositeConverter([
                'cfgname'      => new StringConverter(),
                'cfgnamespace' => new IntegerConverter(),
                'cfgowner'     => new IntegerConverter(),
                'cfgparser'    => new IntegerConverter()
            ]),
            $this->factory->getConverter('pg_ts_config')
        );
    }

    public function testRangeTypeConverterFromMetadata()
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING, false);
        if (version_compare(
            pg_parameter_status($connection->getResource(), 'server_version'), '9.2.0', '<'
        )) {
            $this->markTestSkipped('Connection to PostgreSQL 9.2+ required');
        }
        $connection->setTypeConverterFactory($this->factory);
        $connection->execute("drop type if exists textrange");
        $connection->execute("create type textrange as range (subtype=text, collation=\"C\")");

        $this->assertEquals(
            new RangeConverter(new StringConverter()),
            $this->factory->getConverter('textrange')
        );
    }

    public function testEnumTypeConverterFromMetadata()
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

    public function testMetadataIsStoredInCache()
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }

        /* @var $mockPool CacheItemPoolInterface|\PHPUnit_Framework_MockObject_MockObject */
        $mockPool = $this->getMock('Psr\Cache\CacheItemPoolInterface');
        /* @var $mockItem CacheItemInterface|\PHPUnit_Framework_MockObject_MockObject */
        $mockItem = $this->getMock('Psr\Cache\CacheItemInterface');

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

        $this->assertEquals(new IntegerConverter(), $this->factory->getConverter(23));
    }

    public function testMetadataIsLoadedFromCache()
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }

        /* @var $mockPool CacheItemPoolInterface|\PHPUnit_Framework_MockObject_MockObject */
        $mockPool = $this->getMock('Psr\Cache\CacheItemPoolInterface');
        /* @var $mockItem CacheItemInterface|\PHPUnit_Framework_MockObject_MockObject */
        $mockItem = $this->getMock('Psr\Cache\CacheItemInterface');

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

        $this->factory->registerConverter(
            'sad_spirit\pg_wrapper\converters\containers\HstoreConverter', 'blah','blah'
        );
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);
        $connection->setMetadataCache($mockPool)
            ->setTypeConverterFactory($this->factory);

        $this->assertEquals(new HstoreConverter(), $this->factory->getConverter(123456));
    }

    public function testConfiguresTypeConverterArgumentUsingConnection()
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING, false);
        $connection->setTypeConverterFactory($this->factory);

        $mockConverter = $this->getMock('\sad_spirit\pg_wrapper\converters\ByteaConverter');
        $mockConverter->expects($this->once())
            ->method('setConnectionResource');

        $this->assertSame($mockConverter, $this->factory->getConverter($mockConverter));
    }

    public function getBuiltinTypeConverters()
    {
        return [
            ['int4',                    new IntegerConverter()],
            ['pg_cAtalOg.iNt4',         new IntegerConverter()],
            ['"pg_catalog" . "int4" ',  new IntegerConverter()],
            ['hSTOre',                  new HstoreConverter()],
            ['pUblic.hstore',           new HstoreConverter()],
            [' "public" . "hstore"',    new HstoreConverter()],
            ['tsrange',                 new RangeConverter(new TimeStampConverter())],
            ['pg_catalog.int4range',    new RangeConverter(new IntegerConverter())]
        ];
    }

    public function getSqlStandardTypeConverters()
    {
        return [
            ['integer',                    new IntegerConverter()],
            ['DOUBLE   precision',         new FloatConverter()],
            ["timestamp  WITH\ntime zone", new TimeStampTzConverter()],
            ['time without time zone',     new TimeConverter()]
        ];
    }

    public function getInvalidTypeNames()
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