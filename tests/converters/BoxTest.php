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

use sad_spirit\pg_wrapper\converters\geometric\BoxConverter;
use sad_spirit\pg_wrapper\{
    exceptions\TypeConversionException,
    exceptions\InvalidArgumentException,
    types\Box,
    types\Point
};

/**
 * Unit test for 'box' geometric type converter
 *
 * @extends TypeConverterTestCase<BoxConverter>
 */
class BoxTest extends TypeConverterTestCase
{
    public function setUp(): void
    {
        $this->converter = new BoxConverter();
    }

    public static function valuesBoth(): array
    {
        return [
            [null, null],
            ['((1.2,3.4),(5.6,7.8))', new Box(new Point(1.2, 3.4), new Point(5.6, 7.8))]
        ];
    }

    public static function valuesFrom(): array
    {
        return [
            ['(1.2, 3.4) , (5.6 ,7.8 )', new Box(new Point(1.2, 3.4), new Point(5.6, 7.8))],
            ['1.2 , 3.4, 5.6 ,7.8 ',     new Box(new Point(1.2, 3.4), new Point(5.6, 7.8))],
            ['1.2, 3.4, 5.6',            new TypeConversionException()],
            ['(1.2 , 3.4, 5.6 ,7.8',     new TypeConversionException()],
            ['[(1.2,3.4),(5.6,7.8)]',    new TypeConversionException()],
            ['((1.2,foo),(5.6,7.8))',    new TypeConversionException()]
        ];
    }

    public static function valuesTo(): array
    {
        return [
            ['((1.2,3.4),(5.6,7.8))',        [[1.2, 3.4], [5.6, 7.8]]],
            ['((1.2,3.4),(5.6,7.8))',        [[1.2, 3.4], new Point(5.6, 7.8)]],
            [new TypeConversionException(),  'string'],
            [new InvalidArgumentException(), []],
            [new \TypeError(),               [[1.2, 'foo'], [3.4, 5.6]]],
            [new InvalidArgumentException(), [[1.2], [3.4, 5.6]]],
            [new InvalidArgumentException(), [[1.2, 3.4]]]
        ];
    }
}
