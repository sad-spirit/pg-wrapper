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

use sad_spirit\pg_wrapper\converters\geometric\PointConverter,
    sad_spirit\pg_wrapper\types\Point,
    sad_spirit\pg_wrapper\exceptions\TypeConversionException,
    sad_spirit\pg_wrapper\exceptions\InvalidArgumentException;

/**
 * Unit test for 'point' geometric type converter
 */
class PointTest extends TypeConverterTestCase
{
    public function setUp()
    {
        $this->converter = new PointConverter();
    }

    protected function valuesBoth()
    {
        return array(
            array(null,        null),
            array('(0,0)',     new Point(0, 0)),
            array('(1,2)',     new Point(1, 2)),
            array('(0.6,0.3)', new Point(0.6, 0.3)),
        );
    }

    protected function valuesFrom()
    {
        return array(
            array('0,3.2',       new Point(0, 3.2)),
            array(' 0.1 , 3.2 ', new Point(0.1, 3.2)),
            array('(0,0',        new TypeConversionException()),
            array('0,0)',        new TypeConversionException()),
            array('[0,0]',       new TypeConversionException())
        );
    }

    protected function valuesTo()
    {
        return array(
            array('(1,2)',                       array('y' => 2, 'x' => 1)),
            array('(3,4)',                       array(3, 4)),
            array(new TypeConversionException(), 1),
            array(new TypeConversionException(), 'point'),
            array(new InvalidArgumentException(), array(1)),
            array(new InvalidArgumentException(), array(1, 1, 1)),
            array(new InvalidArgumentException(), array()),
            array(new InvalidArgumentException(), array(2, 'string')),
        );
    }
}
