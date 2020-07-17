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
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

namespace sad_spirit\pg_wrapper\tests;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_wrapper\{
    converters\StubTypeConverterFactory,
    converters\StubConverter,
    converters\IntegerConverter,
    Connection
};
use sad_spirit\pg_wrapper\converters\datetime\TimeStampConverter;


class StubTypeConverterFactoryTest extends TestCase
{
    /**
     * @var StubTypeConverterFactory
     */
    protected $factory;

    public function setUp(): void
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
            $this->factory->getConverterForTypeSpecification($type)
        );
    }

    public function testReturnsTypeConverterArgument()
    {
        $converter = new IntegerConverter();
        $this->assertSame($converter, $this->factory->getConverterForTypeSpecification($converter));
    }

    public function testConfiguresTypeConverterArgumentUsingConnection()
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING, false);
        $connection->setTypeConverterFactory($this->factory);

        $mockConverter = $this->getMockBuilder(TimeStampConverter::class)
            ->getMock();
        $mockConverter->expects($this->once())
            ->method('setConnectionResource');

        $this->assertSame($mockConverter, $this->factory->getConverterForTypeSpecification($mockConverter));
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
