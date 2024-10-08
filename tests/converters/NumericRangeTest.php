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
