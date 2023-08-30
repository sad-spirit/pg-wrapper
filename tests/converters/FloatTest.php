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

    public function valuesBoth(): array
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

    public function valuesFrom(): array
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

    public function valuesTo(): array
    {
        return [
            ['2.3', '2,3'],
            [new TypeConversionException(), ''],
            [new TypeConversionException(), 'string'],
            [new TypeConversionException(), [1]],
        ];
    }
}
