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
    sad_spirit\pg_wrapper\converters\containers\ArrayConverter,
    sad_spirit\pg_wrapper\converters\containers\CompositeConverter,
    sad_spirit\pg_wrapper\converters\containers\HstoreConverter,
    sad_spirit\pg_wrapper\converters\containers\RangeConverter,
    sad_spirit\pg_wrapper\converters\datetime\TimeConverter,
    sad_spirit\pg_wrapper\converters\datetime\TimeStampConverter,
    sad_spirit\pg_wrapper\converters\datetime\TimeStampTzConverter,
    sad_spirit\pg_wrapper\converters\FloatConverter,
    sad_spirit\pg_wrapper\converters\IntegerConverter,
    sad_spirit\pg_wrapper\converters\StringConverter,
    sad_spirit\pg_wrapper\converters\geometric\PointConverter,
    sad_spirit\pg_wrapper\TypeConverterFactory,
    sad_spirit\pg_wrapper\exceptions\InvalidArgumentException,
    Psr\Cache\CacheItemPoolInterface,
    Psr\Cache\CacheItemInterface;

/**
 * Unit test for TypeConverterFactory class
 */
class TypeConverterFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var TypeConverterFactory
     */
    protected $factory;

    public function setUp()
    {
        $this->factory = new TypeConverterFactory();
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
        $this->assertEquals(new CompositeConverter(array(
                'num'     => new IntegerConverter(),
                'string'  => new StringConverter(),
                'strings' => new ArrayConverter(new StringConverter()),
                'coord'   => new PointConverter()
            )),
            $this->factory->getConverter(array(
                'num'     => 'integer',
                'string'  => '"varchar"',
                'strings' => 'text[]',
                'coord'   => 'pg_catalog.point'
            ))
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
            new CompositeConverter(array(
                'cfgname'      => new StringConverter(),
                'cfgnamespace' => new IntegerConverter(),
                'cfgowner'     => new IntegerConverter(),
                'cfgparser'    => new IntegerConverter()
            )),
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
            ->will($this->returnValue(array(
                'composite' => array(),
                'array'     => array(),
                'range'     => array(),
                'names'     => array('blah' => array('blah' => 123456))
            )));

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

    public function getBuiltinTypeConverters()
    {
        return array(
            array('int4',                    new IntegerConverter()),
            array('pg_cAtalOg.iNt4',         new IntegerConverter()),
            array('"pg_catalog" . "int4" ',  new IntegerConverter()),
            array('hSTOre',                  new HstoreConverter()),
            array('pUblic.hstore',           new HstoreConverter()),
            array(' "public" . "hstore"',    new HstoreConverter()),
            array('tsrange',                 new RangeConverter(new TimeStampConverter())),
            array('pg_catalog.int4range',    new RangeConverter(new IntegerConverter()))
        );
    }

    public function getSqlStandardTypeConverters()
    {
        return array(
            array('integer',                    new IntegerConverter()),
            array('DOUBLE   precision',         new FloatConverter()),
            array("timestamp  WITH\ntime zone", new TimeStampTzConverter()),
            array('time without time zone',     new TimeConverter())
        );
    }

    public function getInvalidTypeNames()
    {
        return array(
            array('',             'Missing type name'),
            array('foo.',         'Missing type name'),
            array('foo bar',      'Unexpected identifier'),
            array('foo "bar"',    'Unexpected double-quoted string'),
            array('foo[bar]',     'Invalid array specification'),
            array('foo[',         'Invalid array specification'),
            array('[]',           'Invalid array specification'),
            array('.foo',         'Extra dots'),
            array('foo.bar.baz',  'Extra dots'),
            array('foo.""',       'Invalid double-quoted string'),
            array('foo."bar',     'Invalid double-quoted string'),
            array('foo(666)',     'Unexpected symbol')
        );
    }
}