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

use sad_spirit\pg_wrapper\converters\geometric\LSegConverter;
use sad_spirit\pg_wrapper\{
    exceptions\InvalidArgumentException,
    exceptions\TypeConversionException,
    types\LineSegment,
    types\Point
};

/**
 * Unit test for 'lseg' geometric type converter
 *
 * @extends TypeConverterTestCase<LSegConverter>
 */
class LSegTest extends TypeConverterTestCase
{
    public function setUp(): void
    {
        $this->converter = new LSegConverter();
    }

    public function valuesBoth(): array
    {
        return [
            [null, null],
            ['[(1.2,3.4),(5.6,7.8)]', new LineSegment(new Point(1.2, 3.4), new Point(5.6, 7.8))]
        ];
    }

    public function valuesFrom(): array
    {
        return [
            ['((1.2,3.4),(5.6,7.8))',    new LineSegment(new Point(1.2, 3.4), new Point(5.6, 7.8))],
            ['(1.2, 3.4) , (5.6 ,7.8 )', new LineSegment(new Point(1.2, 3.4), new Point(5.6, 7.8))],
            ['1.2 , 3.4, 5.6 ,7.8 ',     new LineSegment(new Point(1.2, 3.4), new Point(5.6, 7.8))],
            ['1.2, 3.4, 5.6',            new TypeConversionException()],
            ['(1.2 , 3.4, 5.6 ,7.8',     new TypeConversionException()],
            ['((1.2,foo),(5.6,7.8))',    new TypeConversionException()]
        ];
    }

    public function valuesTo(): array
    {
        return [
            ['[(1.2,3.4),(5.6,7.8)]',        [[1.2, 3.4], [5.6, 7.8]]],
            ['[(1.2,3.4),(5.6,7.8)]',        [new Point(1.2, 3.4), [5.6, 7.8]]],
            [new TypeConversionException(),  'string'],
            [new InvalidArgumentException(), []],
            [new \TypeError(),               [[1.2, 'foo'], [3.4, 5.6]]],
            [new InvalidArgumentException(), [[1.2], [3.4, 5.6]]],
            [new InvalidArgumentException(), [[1.2, 3.4]]]
        ];
    }
}
