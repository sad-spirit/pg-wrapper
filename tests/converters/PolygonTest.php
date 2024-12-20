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

use sad_spirit\pg_wrapper\converters\geometric\PolygonConverter;
use sad_spirit\pg_wrapper\{
    exceptions\TypeConversionException,
    exceptions\InvalidArgumentException,
    types\Point,
    types\Polygon
};

/**
 * Unit test for 'polygon' geometric type converter
 *
 * @extends TypeConverterTestCase<PolygonConverter>
 */
class PolygonTest extends TypeConverterTestCase
{
    public function setUp(): void
    {
        $this->converter = new PolygonConverter();
    }

    public static function valuesBoth(): array
    {
        return [
            [null, null],
            ['((1,2))', new Polygon(new Point(1, 2))],
            ['((1,2),(1.2,2.3))', new Polygon(new Point(1, 2), new Point(1.2, 2.3))],
        ];
    }

    public static function valuesFrom(): array
    {
        return [
            ['1, 2,  3  , 4 ', new Polygon(new Point(1, 2), new Point(3, 4))],
            ['(1, 2, 3,4)',    new Polygon(new Point(1, 2), new Point(3, 4))],
            ['(1, 2',          new TypeConversionException()],
            ['[(1,2)]',        new TypeConversionException()]
        ];
    }

    public static function valuesTo(): array
    {
        return [
            ['((1,2))',                      [[1, 2]]],
            ['((3,4))',                      [new Point(3, 4)]],
            [new TypeConversionException(),  1],
            [new InvalidArgumentException(), ['point']],
            [new InvalidArgumentException(), [[1]]],
            [new InvalidArgumentException(), [[1, 1, 1]]],
            [new \TypeError(),               [[2, 'string'], null]],
            [new InvalidArgumentException(), [null, [[1, 2]]]],
        ];
    }
}
