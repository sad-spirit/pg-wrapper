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
}
