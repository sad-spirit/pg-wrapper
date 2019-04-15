<?php
/**
 * Wrapper for PHP's pgsql extension providing conversion of complex DB types
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-wrapper/master/LICENSE
 *
 * @package   sad_spirit\pg_wrapper
 * @copyright 2014-2017 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

namespace sad_spirit\pg_wrapper\tests\converters;

use sad_spirit\pg_wrapper\converters\containers\CompositeConverter,
    sad_spirit\pg_wrapper\converters\containers\ArrayConverter,
    sad_spirit\pg_wrapper\converters\geometric\PointConverter,
    sad_spirit\pg_wrapper\converters\IntegerConverter,
    sad_spirit\pg_wrapper\converters\StringConverter,
    sad_spirit\pg_wrapper\exceptions\TypeConversionException,
    sad_spirit\pg_wrapper\types\Point;

/**
 * Unit test for composite (row) type converter
 */
class CompositeTest extends TypeConverterTestCase
{
    public function setUp()
    {
        $this->converter = new CompositeConverter([
            'num'     => new IntegerConverter(),
            'string'  => new StringConverter(),
            'strings' => new ArrayConverter(new StringConverter()),
            'coord'   => new PointConverter()
        ]);
    }

    protected function valuesBoth()
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

    protected function valuesFrom()
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

    protected function valuesTo()
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
