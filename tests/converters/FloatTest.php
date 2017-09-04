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

use sad_spirit\pg_wrapper\converters\FloatConverter,
    sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Unit test for float type converter
 */
class FloatTest extends TypeConverterTestCase
{
    public function setUp()
    {
        $this->converter = new FloatConverter();
    }

    public function testConvertNaN()
    {
        $this->assertTrue(is_nan($this->converter->input('NaN')));
        $this->assertEquals('NaN', $this->converter->output(floatval(NAN)));
    }

    public function testConvertInfinite()
    {
        $this->assertTrue(is_infinite($this->converter->input('Infinity')));
        $this->assertEquals('-Infinity', $this->converter->output(-floatval(INF)));
    }

    protected function valuesBoth()
    {
        return array(
            array(null, null),
            array('1', 1),
            array('2', 2),
            array('2.3', 2.3),
            array('0', 0),
            array('-20', -20),
        );
    }

    protected function valuesFrom()
    {
        return array(
            array('1', 1.0),
            array('1', '1'),
            array('1', '1.0'),
            array('2', '2'),
            array('2.3', '2.3'),
            array('0', '0'),
            array('-20', '-20'),
        );
    }

    protected function valuesTo()
    {
        return array(
            array('2.3', '2,3'),
            array(new TypeConversionException(), ''),
            array(new TypeConversionException(), 'string'),
            array(new TypeConversionException(), array(1)),
        );
    }
}
