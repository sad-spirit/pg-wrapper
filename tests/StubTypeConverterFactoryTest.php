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

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_wrapper\{
    converters\StubTypeConverterFactory,
    converters\StubConverter,
    converters\IntegerConverter,
    Connection,
    exceptions\RuntimeException
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
    public function testReturnsStubConverterForAnyType($type): void
    {
        $this->assertEquals(
            new StubConverter(),
            $this->factory->getConverterForTypeSpecification($type)
        );
    }

    public function testReturnsTypeConverterArgument(): void
    {
        $converter = new IntegerConverter();
        $this->assertSame($converter, $this->factory->getConverterForTypeSpecification($converter));
    }

    public function testConfiguresTypeConverterArgumentUsingConnection(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING, false);
        $connection->setTypeConverterFactory($this->factory);

        $mockConverter = $this->createMock(TimeStampConverter::class);
        $mockConverter->expects($this->once())
            ->method('setConnection');

        $this->assertSame($mockConverter, $this->factory->getConverterForTypeSpecification($mockConverter));
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

    public function getTypeSpecifications(): array
    {
        return [
            ['foo.bar'],
            [666],
            [['foo', 'bar']],
            [new \stdClass()]
        ];
    }
}
