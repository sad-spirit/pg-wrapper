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
 * @copyright 2014-2024 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

declare(strict_types=1);

namespace sad_spirit\pg_wrapper\tests\converters;

use sad_spirit\pg_wrapper\{
    converters\IntegerConverter,
    exceptions\TypeConversionException
};

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

    /**
     * Tests for non-decimal literals and underscore separators
     *
     * @param string $literal
     * @return void
     * @dataProvider postgres16ValidIntegerLiterals
     */
    public function testAllowNonDecimalLiteralsAndUnderscores(string $literal): void
    {
        $this->converter->setAllowNonDecimalLiteralsAndUnderscores(true);
        $this::assertEquals($literal, $this->converter->output($literal));
    }

    /**
     * Invalid non-decimal literals and underscore separators
     *
     * @param string $literal
     * @return void
     * @dataProvider postgres16InvalidIntegerLiterals
     */
    public function testInvalidNonDecimalLiteralsAndUnderscores(string $literal): void
    {
        $this->converter->setAllowNonDecimalLiteralsAndUnderscores(true);
        $this::expectException(TypeConversionException::class);
        $this->converter->output($literal);
    }

    public function valuesBoth(): array
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

    public function valuesFrom(): array
    {
        return [
            ['1.0', new TypeConversionException()],
            ['NaN', new TypeConversionException()]
        ];
    }

    public function valuesTo(): array
    {
        return [
            [new TypeConversionException(), ''],
            [new TypeConversionException(), 'string'],
            [new TypeConversionException(), [1]],
        ];
    }

    public function postgres16ValidIntegerLiterals(): array
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

    public function postgres16InvalidIntegerLiterals(): array
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
