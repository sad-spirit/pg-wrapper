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

use sad_spirit\pg_wrapper\converters\geometric\CircleConverter;
use sad_spirit\pg_wrapper\{
    exceptions\TypeConversionException,
    exceptions\InvalidArgumentException,
    types\Circle,
    types\Point
};

/**
 * Unit test for 'circle' geometric type converter
 */
class CircleTest extends TypeConverterTestCase
{
    public function setUp()
    {
        $this->converter = new CircleConverter();
    }

    protected function valuesBoth()
    {
        return [
            [null, null],
            ['<(1.2,3.4),5.6>', new Circle(new Point(1.2, 3.4), 5.6)]
        ];
    }

    protected function valuesFrom()
    {
        return [
            ['((1.2 ,3.4 ) , 5.6)',  new Circle(new Point(1.2, 3.4), 5.6)],
            ['(1.2 ,3.4 ) , 5.6',    new Circle(new Point(1.2, 3.4), 5.6)],
            ['1.2 ,3.4 , 5.6',       new Circle(new Point(1.2, 3.4), 5.6)],
            ['( (1.2 ,3.4 ) ), 5.6', new TypeConversionException()],
            ['1.2, 3.4',             new TypeConversionException()],
            ['(1.2, 3.4, 5.6',       new TypeConversionException()],
            ['1.2, 3.4, 5.6, 7.8',   new TypeConversionException()]
        ];
    }

    protected function valuesTo()
    {
        return [
            ['<(1.2,3.4),5.6>',              ['radius' => 5.6, 'center' => [1.2, 3.4]]],
            ['<(1.2,3.4),5.6>',              [new Point(1.2, 3.4), 5.6]],
            [new TypeConversionException(),  'string'],
            [new InvalidArgumentException(), []],
            [new InvalidArgumentException(), [[1.2, 'foo'], 3.4]],
            [new InvalidArgumentException(), [[1.2, 3.4], 'bar']],
            [new InvalidArgumentException(), [[1.2, 3.4]]]
        ];
    }
}