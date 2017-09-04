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

use sad_spirit\pg_wrapper\converters\geometric\LineConverter,
    sad_spirit\pg_wrapper\types\Line,
    sad_spirit\pg_wrapper\exceptions\InvalidArgumentException,
    sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Unit test for 'line' geometric type (9.4+) converter
 */
class LineTest extends TypeConverterTestCase
{
    public function setUp()
    {
        $this->converter = new LineConverter();
    }

    protected function valuesBoth()
    {
        return array(
            array(null,             null),
            array('{1.2,3.4,5.6}',  new Line(1.2, 3.4, 5.6))
        );
    }

    protected function valuesFrom()
    {
        return array(
            array('  {  1.2 , 3.4 ,    5.6}   ', new Line(1.2, 3.4, 5.6)),
            array('{ 1 , 2 , 3, 4}',             new TypeConversionException()),
            array('{1, 2}',                      new TypeConversionException()),
            array('{1,2,3}+',                    new TypeConversionException()),
            array('{1,2,3',                      new TypeConversionException())
        );
    }

    protected function valuesTo()
    {
        return array(
            array('{1.2,3.4,5.6}',                array('C' => 5.6, 'A' => 1.2, 'B' => 3.4)),
            array('{1.2,3.4,5.6}',                array(1.2, 3.4, 5.6)),
            array(new TypeConversionException(),  'a line'),
            array(new InvalidArgumentException(), array(2, 4))
        );
    }
}