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

use sad_spirit\pg_wrapper\converters\containers\HstoreConverter,
    sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Unit test for hstore type (from contrib) converter
 */
class HstoreTest extends TypeConverterTestCase
{
    public function setUp()
    {
        $this->converter = new HstoreConverter();
    }

    protected function valuesBoth()
    {
        return array(
            array(null, null),
            array('', array()),
            array('"0"=>"b"', array('0'=>'b')),
            array('"a"=>"b"', array('a'=>'b')),
            array('"a"=>"b", "b"=>"\\"a"', array('a'=>'b', 'b'=>'"a')),
            array('"a"=>NULL, "x"=>"123"', array('a'=>null, 'x'=>'123')),
            array('"a"=>"null", "b"=>NULL', array('a' => 'null', 'b' => null))
        );
    }

    protected function valuesFrom()
    {
        return array(
            array('a=>b', array('a' => 'b')),
            array('a   =>    b', array('a' => 'b')),
            array('a   =>    b, 4=>  "\\"x\\"y\\"z\\\\a"', array('a' => 'b', '4' => '"x"y"z\\a')),
            array('a=>b,', array('a' => 'b')),
            array(',a=>b', array(',a' => 'b')),
            array('a=>b=>c', array('a' => 'b=>c')),
            array('a', new TypeConversionException()),
            array('a,b', new TypeConversionException()),
            array('a=>b,,,,,,', new TypeConversionException())
        );
    }

    protected function valuesTo()
    {
        return array(
            array(new TypeConversionException(), 1),
            array('"0"=>"1", "1"=>"a", "2"=>"xyz"', array(1, "a", 'xyz')),
            array('"0"=>"1", "1"=>"a", "test"=>"xyz"', array(1, "a", 'test' => 'xyz')),
        );
    }
}
