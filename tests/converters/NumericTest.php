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
    converters\NumericConverter,
    exceptions\TypeConversionException
};
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Unit test for numeric type converter
 *
 * @extends TypeConverterTestCase<NumericConverter>
 */
class NumericTest extends TypeConverterTestCase
{
    public function setUp(): void
    {
        $this->converter = new NumericConverter();
    }

    public function testConvertNaN(): void
    {
        $this->assertTrue(\is_nan($this->converter->input('NAN')));
        $this->assertEquals('NaN', $this->converter->output(\NAN));
    }

    public function testConvertInfinity(): void
    {
        $this::assertTrue(\is_infinite($this->converter->input('-Infinity')));
        $this::assertEquals('Infinity', $this->converter->output(\INF));
    }

    public function testLocaleIndependentConversion(): void
    {
        try {
            \setlocale(\LC_NUMERIC, 'Russian', 'ru_RU', 'ru_RU.UTF-8');
            $this::assertEquals('1.234', $this->converter->output(1.234));
        } finally {
            \setlocale(\LC_NUMERIC, 'C');
        }
    }

    #[DataProvider('postgres16ValidNumericLiterals')]
    public function testAllowNonDecimalLiteralsAndUnderscores(string $literal): void
    {
        $this->converter->setAllowNonDecimalLiteralsAndUnderscores(true);
        $this::assertEquals($literal, $this->converter->output($literal));
    }

    #[DataProvider('postgres16InvalidNumericLiterals')]
    public function testInvalidNonDecimalLiteralsAndUnderscores(string $literal): void
    {
        $this->converter->setAllowNonDecimalLiteralsAndUnderscores(true);
        $this::expectException(TypeConversionException::class);
        $this->converter->output($literal);
    }

    public static function valuesBoth(): array
    {
        return [
            [null,   null],
            ['10',   '10'],
            ['-20',  '-20'],
            ['3.5',  '3.5']
        ];
    }

    public static function valuesFrom(): array
    {
        return [
            ["\v1.0\f", '1.0'],
            ['',     new TypeConversionException()],
            ['blah', new TypeConversionException()]
        ];
    }

    public static function valuesTo(): array
    {
        return [
            ['2.3',                         '2,3'],
            [new TypeConversionException(), ''],
            [new TypeConversionException(), 'fuck'],
            [new TypeConversionException(), []]
        ];
    }

    public static function postgres16ValidNumericLiterals(): array
    {
        return [
            ['0b_10_0101'],

            ['1_000.000_005'],
            ['1_000.'],
            ['.000_005'],
            ['1_000.5e0_1']
        ];
    }

    public static function postgres16InvalidNumericLiterals(): array
    {
        return [
            ['0x0y'],
            ['100__000'],

            ['1_000_.5'],
            ['1_000._5'],
            ['1_000.5_'],
            ['1_000.5e_1']
        ];
    }
}
