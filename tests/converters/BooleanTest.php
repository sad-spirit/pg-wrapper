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

use sad_spirit\pg_wrapper\converters\BooleanConverter;

/**
 * Unit test for boolean type converter
 */
class BooleanTest extends TypeConverterTestCase
{
    public function setUp()
    {
        $this->converter = new BooleanConverter();
    }

    protected function valuesBoth()
    {
        return array(
            array(null, null),
            array('t', true),
            array('f', false),
        );
    }

    protected function valuesFrom()
    {
        return array(
            array('1', true),
            array('0', false),
        );
    }

    protected function valuesTo()
    {
        return array(
            array('t', 'true'),
            array('t', 1),
            array('t', -1),
            array('t', '1'),
            array('t', '1.1'),
            array('t', '0.0'),
            array('t', 'string'),
            array('t', array('value')),
            array('t', array(0)),

            array('f', 'false'),
            array('f', 0),
            array('f', '0'),
            array('f', array()),
        );
    }
}
