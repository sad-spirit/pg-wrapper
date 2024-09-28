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

use sad_spirit\pg_wrapper\converters\{
    containers\CompositeConverter,
    containers\ArrayConverter,
    geometric\PointConverter,
    IntegerConverter,
    StringConverter
};
use sad_spirit\pg_wrapper\{
    exceptions\TypeConversionException,
    types\Point
};

/**
 * Unit test for composite (row) type converter
 *
 * @extends TypeConverterTestCase<CompositeConverter>
 */
class CompositeTest extends TypeConverterTestCase
{
    public function setUp(): void
    {
        $this->converter = new CompositeConverter([
            'num'     => new IntegerConverter(),
            'string'  => new StringConverter(),
            'strings' => new ArrayConverter(new StringConverter()),
            'coord'   => new PointConverter()
        ]);
    }

    public static function valuesBoth(): array
    {
        return [
            [null, null],
            [
                '(,,,)',
                [
                    'num'       => null,
                    'string'    => null,
                    'strings'   => null,
                    'coord'     => null,
                ]
            ],
            [
                '(,"test",,)',
                [
                    'num'       => null,
                    'string'    => 'test',
                    'strings'   => null,
                    'coord'     => null,
                ]
            ],
            [
                '("7","test","{""a\\\\""b"",""b\\\\\\\\c""}","(1.2,4.2)")',
                [
                    'num'       => 7,
                    'string'    => 'test',
                    'strings'   => ['a"b', 'b\\c'],
                    'coord'     => new Point(1.2, 4.2),
                ]
            ]
        ];
    }

    public static function valuesFrom(): array
    {
        return [
            [
                '(7,test,"{x,y}","(0, 4.2)")',
                [
                    'num'       => '7',
                    'string'    => 'test',
                    'strings'   => ['x', 'y'],
                    'coord'     => new Point(0, 4.2),
                ]],
            ['(,,)', new TypeConversionException()],
            ['(,,,,)', new TypeConversionException()],
        ];
    }

    public static function valuesTo(): array
    {
        return [
            [new TypeConversionException(), 1],
            [new TypeConversionException(), 'Hi!'],
            ['(,,,)', []],
            ['(,,,)', ['x' => 'd']],
            ['("7",,,)', ['num' => '7']],
            ['(,"test",,)', ['string' => 'test']],
            [
                '("5",,"{""Hello,"",""World!""}","(192,4.2)")',
                (object) [
                    'num'       => 5,
                    'coord'     => [192, 4.2],
                    'strings'   => ['Hello,', 'World!'],
                    'junk'      => 'Oh, no!',
                ]
            ]
        ];
    }
}
