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

use sad_spirit\pg_wrapper\converters\geometric\PathConverter,
    sad_spirit\pg_wrapper\exceptions\InvalidArgumentException,
    sad_spirit\pg_wrapper\exceptions\TypeConversionException,
    sad_spirit\pg_wrapper\types\Path,
    sad_spirit\pg_wrapper\types\Point;

/**
 * Unit test for 'path' geometric type converter
 */
class PathTest extends TypeConverterTestCase
{
    public function setUp()
    {
        $this->converter = new PathConverter();
    }

    protected function valuesBoth()
    {
        return array(
            array(null,                null),
            array('[(1,2)]',           new Path(array(new Point(1, 2)), true)),
            array('[(1,2),(1.2,2.3)]', new Path(array(new Point(1, 2), new Point(1.2, 2.3)), true)),
            array('((1,2),(1.2,2.3))', new Path(array(new Point(1, 2), new Point(1.2, 2.3)), false))
        );
    }

    protected function valuesFrom()
    {
        return array(
            array('1,2,3,4',     new Path(array(new Point(1, 2), new Point(3, 4)), false)),
            array('(1,2,3,4,5)', new TypeConversionException()),
            array('([1,2],3,4)', new TypeConversionException()),
        );
    }

    protected function valuesTo()
    {
        return array(
            array('((1,2),(1.2,2.3))',            array(array(1, 2), array(1.2, 2.3))),
            array('[(3,4),(5,6)]',                array('open' => true, array(3, 4), array(5, 6))),
            array(new TypeConversionException(),  1),
            array(new InvalidArgumentException(), array('point')),
            array(new InvalidArgumentException(), array(array(1))),
            array(new InvalidArgumentException(), array(array(1, 1, 1))),
            array(new InvalidArgumentException(), array(array(2, 'string'), null)),
            array(new InvalidArgumentException(), array(null, array(array(1, 2)))),
        );
    }
}
