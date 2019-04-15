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
    converters\StubTypeConverterFactory,
    converters\StubConverter,
    converters\IntegerConverter,
    Connection
};


class StubTypeConverterFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var StubTypeConverterFactory
     */
    protected $factory;

    public function setUp()
    {
        $this->factory = new StubTypeConverterFactory();
    }

    /**
     * @dataProvider getTypeSpecifications
     * @param mixed $type
     */
    public function testReturnsStubConverterForAnyType($type)
    {
        $this->assertEquals(
            new StubConverter(),
            $this->factory->getConverter($type)
        );
    }

    public function testReturnsTypeConverterArgument()
    {
        $converter = new IntegerConverter();
        $this->assertSame($converter, $this->factory->getConverter($converter));
    }

    public function testConfiguresTypeConverterArgumentUsingConnection()
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING, false);
        $connection->setTypeConverterFactory($this->factory);

        $mockConverter = $this->getMockBuilder('\sad_spirit\pg_wrapper\converters\ByteaConverter')
            ->getMock();
        $mockConverter->expects($this->once())
            ->method('setConnectionResource');

        $this->assertSame($mockConverter, $this->factory->getConverter($mockConverter));
    }

    public function getTypeSpecifications()
    {
        return [
            ['foo.bar'],
            [666],
            [['foo', 'bar']],
            [new \stdClass()]
        ];
    }
}