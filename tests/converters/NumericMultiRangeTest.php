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

    public function valuesBoth(): array
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

    public function valuesFrom(): array
    {
        return [
            ['   {    [1     , 2]  }',  new NumericMultiRange(new NumericRange('1', '2', true, true))],
            ['[1,2]',                   new TypeConversionException()],
            ['{[1,2]',                  new TypeConversionException()],
            ['{[1,2] [3,4]}',           new TypeConversionException()],
            ['{[2,1]}',                 new InvalidArgumentException()]
        ];
    }

    public function valuesTo(): array
    {
        return [
            ['{["2","3")}',                     [[2, 3]]],
            [new InvalidArgumentException(),    [[3, 2]]],
            [new TypeConversionException(),     new \stdClass()]
        ];
    }
}
