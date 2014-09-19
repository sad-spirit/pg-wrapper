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

use sad_spirit\pg_wrapper\exceptions\InvalidArgumentException,
    sad_spirit\pg_wrapper\types\Tid,
    sad_spirit\pg_wrapper\converters\TidConverter,
    sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Unit test for tid type converter
 */
class TidTest extends TypeConverterTestCase
{
    public function setUp()
    {
        $this->converter = new TidConverter();
    }

    protected function valuesBoth()
    {
        return array(
            array('(0,0)', new Tid(0, 0)),
            array('(1,2)', new Tid(1, 2))
        );
    }

    protected function valuesFrom()
    {
        return array(
            array(' ( 3 , 4 ) ', new Tid(3, 4)),
            array('666',         new TypeConversionException()),
            array('(5)',         new TypeConversionException()),
            array('(1,2,3)',     new TypeConversionException()),
            array('(1,2',        new TypeConversionException()),
            array('(-1,1)',      new InvalidArgumentException()),
            array('(1, -1)',     new InvalidArgumentException())
        );
    }

    protected function valuesTo()
    {
        return array(
            array('(1,2)',                       array('tuple' => 2, 'block' => 1)),
            array('(1,2)',                       array(1, 2)),
            array(new TypeConversionException(), 'a string'),
            array(new InvalidArgumentException(), array(1)),
            array(new InvalidArgumentException(), array(1, 2, 3)),
            array(new InvalidArgumentException(), array(-1, 2)),
            array(new InvalidArgumentException(), array(1, 'foo'))
        );
    }
}