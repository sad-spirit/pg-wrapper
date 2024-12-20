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

use sad_spirit\pg_wrapper\converters\containers\RangeConverter;
use sad_spirit\pg_wrapper\{
    converters\NumericConverter,
    exceptions\InvalidArgumentException,
    exceptions\TypeConversionException,
    types\NumericRange
};

/**
 * Unit test for numrange type converter
 *
 * @extends TypeConverterTestCase<RangeConverter>
 */
class NumericRangeTest extends TypeConverterTestCase
{
    public function setUp(): void
    {
        $this->converter = new RangeConverter(new NumericConverter());
    }

    public static function valuesBoth(): array
    {
        return [
            ['empty',  NumericRange::createEmpty()],
            ['(,)',    new NumericRange()],
            ['("1",)', new NumericRange(1, null, false, true)],
            ['(,"2")', new NumericRange(null, 2, false, false)],
            ['["0","Infinity"]', new NumericRange(0, \INF, true, true)]
        ];
    }

    public static function valuesFrom(): array
    {
        return [
            [' (    2    ,   3 )', new NumericRange(2, 3, false, false)],
            ['[2, a]',             new TypeConversionException()],
            ['(3,2)',              new InvalidArgumentException()]
        ];
    }

    public static function valuesTo(): array
    {
        return [
            ['["2","3"]',                    ['upper' => 3, 'lower' => 2, 'upperInclusive' => true]],
            ['["2","3")',                    [2, 3]],
            [new InvalidArgumentException(), [3, 2]],
            [new TypeConversionException(),  new \stdClass()]
        ];
    }
}
