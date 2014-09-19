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
 * @copyright 2014 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

namespace sad_spirit\pg_wrapper\tests\converters;

use sad_spirit\pg_wrapper\converters\containers\ArrayConverter,
    sad_spirit\pg_wrapper\converters\geometric\PointConverter,
    sad_spirit\pg_wrapper\exceptions\TypeConversionException,
    sad_spirit\pg_wrapper\exceptions\InvalidArgumentException,
    sad_spirit\pg_wrapper\types\Point;

/**
 * Unit test for a combination of array and point type converters
 */
class PointArrayTest extends TypeConverterTestCase
{
    public function setUp()
    {
        $this->converter = new ArrayConverter(new PointConverter());
    }

    protected function valuesBoth()
    {
        return array(
            array(null, null),
            array('{}', array()),
            array('{NULL,NULL}', array(null, null)),
            array('{NULL,"(0.6,0.3)"}', array(null, new Point(0.6, 0.3))),
            array('{"(0,0)","(1,2)"}', array(new Point(0, 0), new Point(1, 2))),
            array('{"(0.6,0.3)"}', array(new Point(0.6, 0.3))),
        );
    }

    protected function valuesFrom()
    {
        return array(
            array('{NULL, NULL}', array(null, null)),
            array('{ {NULL} ,{  NULL," (0,   0)" }}  ', array(array(null), array(null, new Point(0, 0)))),
        );
    }

    protected function valuesTo()
    {
        return array(
            array('{"(1,2)","(3,4)"}',           array(array('y' => 2, 'x' => 1), array(3, 4))),
            array(new TypeConversionException(), 1),
            array(new TypeConversionException(), array('point')),
            array(new InvalidArgumentException(), array(array(1))),
            array(new InvalidArgumentException(), array(array(1, 1, 1))),
            array(new InvalidArgumentException(), array(array(2, 'string'), null)),
            array(new InvalidArgumentException(), array(null, array(array(1, 2)))),
            // the result is accepted by Postgres but probably shouldn't be
            // http://www.postgresql.org/message-id/E1VEETa-0007KM-8O@wrigleys.postgresql.org
            array(new TypeConversionException(), array(array(null), array(null, array(0, 0)))),
            // empty sub-arrays
            array(new InvalidArgumentException(), array(array())),
            array(new InvalidArgumentException(), array(array(), array()))
        );
    }
}
