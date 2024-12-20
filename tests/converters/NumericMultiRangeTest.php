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
    exceptions\InvalidArgumentException,
    exceptions\TypeConversionException,
    types\NumericMultiRange,
    types\NumericRange
};
use sad_spirit\pg_wrapper\converters\containers\MultiRangeConverter;

/**
 * Unit test for nummultirange type converter
 *
 * @extends TypeConverterTestCase<MultiRangeConverter>
 */
class NumericMultiRangeTest extends TypeConverterTestCase
{
    public function setUp(): void
    {
        $this->converter = new MultiRangeConverter(new NumericConverter());
    }

    public static function valuesBoth(): array
    {
        return [
            ['{}',                      new NumericMultiRange()],
            ['{empty}',                 new NumericMultiRange(NumericRange::createEmpty())],
            ['{(,)}',                   new NumericMultiRange(new NumericRange())],
            [
                '{["1","2"],("3","4.5")}',
                new NumericMultiRange(
                    new NumericRange('1', '2', true, true),
                    new NumericRange('3', '4.5', false, false)
                )
            ]
        ];
    }

    public static function valuesFrom(): array
    {
        return [
            ['   {    [1     , 2]  }',  new NumericMultiRange(new NumericRange('1', '2', true, true))],
            ['[1,2]',                   new TypeConversionException()],
            ['{[1,2]',                  new TypeConversionException()],
            ['{[1,2] [3,4]}',           new TypeConversionException()],
            ['{[2,1]}',                 new InvalidArgumentException()]
        ];
    }

    public static function valuesTo(): array
    {
        return [
            ['{["2","3")}',                     [[2, 3]]],
            [new InvalidArgumentException(),    [[3, 2]]],
            [new TypeConversionException(),     new \stdClass()]
        ];
    }
}
