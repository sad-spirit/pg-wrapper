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

use sad_spirit\pg_wrapper\converters\containers\ArrayConverter;
use sad_spirit\pg_wrapper\converters\geometric\BoxConverter;
use sad_spirit\pg_wrapper\exceptions\TypeConversionException;
use sad_spirit\pg_wrapper\types\Box;
use sad_spirit\pg_wrapper\types\Point;

/**
 * Unit test for an array converter configured by 'box' geometric type converter
 *
 * 'box' is the only built-in type having a separator for array elements that is not a comma
 *
 * @extends TypeConverterTestCase<ArrayConverter>
 */
class BoxArrayTest extends TypeConverterTestCase
{
    public function setUp(): void
    {
        $this->converter = new ArrayConverter(new BoxConverter());
    }

    public static function valuesBoth(): array
    {
        return [
            [null, null],
            ['{}', []],
            [
                '{"((1.2,3.4),(5.6,7.8))";"((9.8,7.6),(5.4,3.2))"}',
                [
                    new Box(new Point(1.2, 3.4), new Point(5.6, 7.8)),
                    new Box(new Point(9.8, 7.6), new Point(5.4, 3.2))
                ]
            ]
        ];
    }

    public static function valuesFrom(): array
    {
        return [
            [
                '{1,2,3,4;5,6,7,8}',
                [
                    new Box(new Point(1, 2), new Point(3, 4)),
                    new Box(new Point(5, 6), new Point(7, 8))
                ]
            ],
            ['{(1,2),(3,4),(5,6),(7,8)}', new TypeConversionException()]
        ];
    }

    public static function valuesTo(): array
    {
        return [
            ['{"((1,2),(3,4))";"((5,6),(7,8))"}', [[[1, 2], [3, 4]], [[5, 6], [7, 8]]]]
        ];
    }
}
