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
 * @copyright 2014-2023 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

declare(strict_types=1);

namespace sad_spirit\pg_wrapper\tests\converters;

use sad_spirit\pg_wrapper\{
    converters\NumericConverter,
    exceptions\TypeConversionException
};

/**
 * Unit test for numeric type converter
 */
class NumericTest extends TypeConverterTestCase
{
    public function setUp(): void
    {
        $this->converter = new NumericConverter();
    }

    public function testConvertNaN(): void
    {
        $this->assertTrue(is_nan($this->converter->input('NAN')));
        $this->assertEquals('NaN', $this->converter->output(NAN));
    }

    public function testConvertInfinity(): void
    {
        $this::assertTrue(is_infinite($this->converter->input('-Infinity')));
        $this::assertEquals('Infinity', $this->converter->output(INF));
    }

    public function testLocaleIndependentConversion(): void
    {
        try {
            setlocale(LC_NUMERIC, 'Russian', 'ru_RU', 'ru_RU.UTF-8');
            $this::assertEquals('1.234', $this->converter->output(1.234));
        } finally {
            setlocale(LC_NUMERIC, 'C');
        }
    }

    public function valuesBoth(): array
    {
        return [
            [null,   null],
            ['10',   '10'],
            ['-20',  '-20'],
            ['3.5',  '3.5']
        ];
    }

    public function valuesFrom(): array
    {
        return [
            ['',     new TypeConversionException()],
            ['blah', new TypeConversionException()]
        ];
    }

    public function valuesTo(): array
    {
        return [
            ['2.3',                         '2,3'],
            [new TypeConversionException(), ''],
            [new TypeConversionException(), 'fuck'],
            [new TypeConversionException(), []]
        ];
    }
}
