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
    protected StubTypeConverterFactory $factory;

    public function setUp(): void
    {
        $this->factory = new StubTypeConverterFactory();
    }

    #[DataProvider('getTypeSpecifications')]
    public function testReturnsStubConverterForAnyType(mixed $type): void
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

    public static function getTypeSpecifications(): array
    {
        return [
            ['foo.bar'],
            [666],
            [['foo', 'bar']],
            [new \stdClass()]
        ];
    }
}
