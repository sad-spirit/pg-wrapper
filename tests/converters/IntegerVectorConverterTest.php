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
