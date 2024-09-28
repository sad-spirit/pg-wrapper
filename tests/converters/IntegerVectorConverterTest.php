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

use sad_spirit\pg_wrapper\converters\containers\IntegerVectorConverter;
use sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Unit test for int2vector / oidvector type converter
 *
 * @extends TypeConverterTestCase<IntegerVectorConverter>
 */
class IntegerVectorConverterTest extends TypeConverterTestCase
{
    protected function setUp(): void
    {
        $this->converter = new IntegerVectorConverter();
    }

    public static function valuesBoth(): array
    {
        return [
            [null,  null],
            ['',    []],
            ['1',   [1]],
            ['1 2', [1, 2]]
        ];
    }

    public static function valuesFrom(): array
    {
        return [
            [' 1  2 ',      [1, 2]],
            ['1 foo',       new TypeConversionException()]
        ];
    }

    public static function valuesTo(): array
    {
        return [
            [new TypeConversionException(), [1, 'foo']],
            [new TypeConversionException(), [1, null]]
        ];
    }
}
