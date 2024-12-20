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
    converters\FloatConverter,
    exceptions\TypeConversionException
};

/**
 * Unit test for float type converter
 *
 * @extends TypeConverterTestCase<FloatConverter>
 */
class FloatTest extends TypeConverterTestCase
{
    public function setUp(): void
    {
        $this->converter = new FloatConverter();
    }

    public static function valuesBoth(): array
    {
        return [
            [null, null],
            ['1', 1],
            ['2', 2],
            ['2.3', 2.3],
            ['0', 0],
            ['-20', -20],
        ];
    }

    public static function valuesFrom(): array
    {
        return [
            ['1', 1.0],
            ['1', '1'],
            ['1', '1.0'],
            ['2', '2'],
            ['2.3', '2.3'],
            ['0', '0'],
            ['-20', '-20'],
        ];
    }

    public static function valuesTo(): array
    {
        return [
            ['2.3', '2,3'],
            [new TypeConversionException(), ''],
            [new TypeConversionException(), 'string'],
            [new TypeConversionException(), [1]],
        ];
    }
}
