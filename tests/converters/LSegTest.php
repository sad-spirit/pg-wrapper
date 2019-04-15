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

use sad_spirit\pg_wrapper\exceptions\InvalidArgumentException,
    sad_spirit\pg_wrapper\exceptions\TypeConversionException,
    sad_spirit\pg_wrapper\converters\geometric\LSegConverter,
    sad_spirit\pg_wrapper\types\LineSegment,
    sad_spirit\pg_wrapper\types\Point;

/**
 * Unit test for 'lseg' geometric type converter
 */
class LSegTest extends TypeConverterTestCase
{
    public function setUp()
    {
        $this->converter = new LSegConverter();
    }

    protected function valuesBoth()
    {
        return [
            [null, null],
            ['[(1.2,3.4),(5.6,7.8)]', new LineSegment(new Point(1.2, 3.4), new Point(5.6, 7.8))]
        ];
    }

    protected function valuesFrom()
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

    protected function valuesTo()
    {
        return [
            ['[(1.2,3.4),(5.6,7.8)]',        [[1.2, 3.4], [5.6, 7.8]]],
            ['[(1.2,3.4),(5.6,7.8)]',        [new Point(1.2, 3.4), [5.6, 7.8]]],
            [new TypeConversionException(),  'string'],
            [new InvalidArgumentException(), []],
            [new InvalidArgumentException(), [[1.2, 'foo'], [3.4, 5.6]]],
            [new InvalidArgumentException(), [[1.2], [3.4, 5.6]]],
            [new InvalidArgumentException(), [[1.2, 3.4]]]
        ];
    }
}