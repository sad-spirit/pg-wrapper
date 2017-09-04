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

use sad_spirit\pg_wrapper\converters\StringConverter;

/**
 * Unit test for string type(s) converter
 */
class StringTest extends TypeConverterTestCase
{
    public function setUp()
    {
        $this->converter = new StringConverter();
    }

    protected function valuesBoth()
    {
        return array(
            array(null, null),
            array('', ''),
            array('1', '1'),
            array('2.324', '2.324'),
            array('text', 'text'),
        );
    }

    protected function valuesFrom()
    {
        return array();
    }

    protected function valuesTo()
    {
        return array(
            array('1', 1.0),
            array('-3', -3.00)
        );
    }
}
