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

namespace sad_spirit\pg_wrapper\tests\converters;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use sad_spirit\pg_wrapper\{
    converters\EnumConverter,
    exceptions\InvalidArgumentException,
    exceptions\TypeConversionException
};
use sad_spirit\pg_wrapper\tests\types\{
    IntBackedEnum,
    StringBackedEnum
};

/**
 * Unit test for EnumConverter
 */
class EnumConverterTest extends TestCase
{
    public function testDisallowIntBackedEnum(): void
    {
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('string-backed enum');

        new EnumConverter(IntBackedEnum::class);
    }

    public function testInputValidValue(): void
    {
        $converter = new EnumConverter(StringBackedEnum::class);

        $this::assertSame(StringBackedEnum::YES, $converter->input('yes'));
    }

    public function testInputInvalidValue(): void
    {
        $converter = new EnumConverter(StringBackedEnum::class);

        $this::expectException(TypeConversionException::class);
        $this::expectExceptionMessage("Failed to convert 'wut?'");
        $converter->input('wut?');
    }

    #[DataProvider('validValuesProvider')]
    public function testOutputValidValue(string|StringBackedEnum $value, string $native): void
    {
        $converter = new EnumConverter(StringBackedEnum::class);

        $this::assertSame($native, $converter->output($value));
    }

    #[DataProvider('invalidValuesProvider')]
    public function testOutputInvalidValue(mixed $value, string $message): void
    {
        $converter = new EnumConverter(StringBackedEnum::class);

        $this::expectException(TypeConversionException::class);
        $this::expectExceptionMessage($message);
        $converter->output($value);
    }

    public static function validValuesProvider(): array
    {
        return [
            [StringBackedEnum::MAYBE, 'maybe'],
            ['no',                    'no']
        ];
    }

    public static function invalidValuesProvider(): array
    {
        return [
            ['wut?', "Failed to convert 'wut?'"],
            [666,    "enum or a string"]
        ];
    }
}
