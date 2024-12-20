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

use sad_spirit\pg_wrapper\{
    converters\IntegerConverter,
    exceptions\TypeConversionException
};
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Unit test for integer type converter
 *
 * @extends TypeConverterTestCase<IntegerConverter>
 */
class IntegerTest extends TypeConverterTestCase
{
    public function setUp(): void
    {
        $this->converter = new IntegerConverter();
    }

    #[DataProvider('postgres16ValidIntegerLiterals')]
    public function testAllowNonDecimalLiteralsAndUnderscores(string $literal): void
    {
        $this->converter->setAllowNonDecimalLiteralsAndUnderscores(true);
        $this::assertEquals($literal, $this->converter->output($literal));
    }

    #[DataProvider('postgres16InvalidIntegerLiterals')]
    public function testInvalidNonDecimalLiteralsAndUnderscores(string $literal): void
    {
        $this->converter->setAllowNonDecimalLiteralsAndUnderscores(true);
        $this::expectException(TypeConversionException::class);
        $this->converter->output($literal);
    }

    public static function valuesBoth(): array
    {
        return [
            [null, null],
            ['1', 1],
            ['2', 2],
            ['0', 0],
            ['-20', -20],
            ['9999999999', '9999999999']
        ];
    }

    public static function valuesFrom(): array
    {
        return [
            ["\v1\f", 1],
            ['1.0', new TypeConversionException()],
            ['NaN', new TypeConversionException()]
        ];
    }

    public static function valuesTo(): array
    {
        return [
            [new TypeConversionException(), ''],
            [new TypeConversionException(), 'string'],
            [new TypeConversionException(), [1]],
        ];
    }

    public static function postgres16ValidIntegerLiterals(): array
    {
        return [
            ['0b100101'],
            ['0o273'],
            ['0x42F'],
            ['1_000_000'],
            ['1_2_3'],
            ['0x1EEE_FFFF'],
            ['0o2_73'],
            ['0b_10_0101']
        ];
    }

    public static function postgres16InvalidIntegerLiterals(): array
    {
        return [
            ['123abc'],
            ['0x0o'],

            ['0b'],
            ['1b'],
            ['0b0x'],

            ['0o'],
            ['1o'],
            ['0o0x'],

            ['0x'],
            ['1x'],
            ['0x0y'],
            ['100_'],
            ['100__000']
        ];
    }
}
