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

use sad_spirit\pg_wrapper\{
    converters\ByteaConverter,
    exceptions\TypeConversionException
};

/**
 * Unit test for bytea type converter
 */
class ByteaTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ByteaConverter
     */
    protected $caster;

    protected function setUp()
    {
        $this->caster = new ByteaConverter;
    }

    /**
     * @dataProvider getValuesFrom
     */
    public function testCastFrom($native, $value)
    {
        if ($value instanceof \Exception) {
            $this->setExpectedException(get_class($value));
        }
        $this->assertEquals($value, $this->caster->input($native));
    }

    /**
     * @dataProvider getValuesToHex
     */
    public function testCastToHex($value, $hexEncoded)
    {
        $this->assertEquals($hexEncoded, $this->caster->output($value));
    }

    public function getValuesFrom()
    {
        return [
            [null,                     null],
            ['',                       ''],
            ['\x  ',                   ''],
            ['abc\\000\'\\\\\\001def', "abc\000'\\\001def"],
            ['\x4142000102',           "AB\000\001\002"],
            ['\x4 1',                  new TypeConversionException()],
            ['\x41$2',                 new TypeConversionException()]
        ];
    }

    public function getValuesToHex()
    {
        return [
            [null,         null],
            ['',           '\x'],
            ["\000'\\",    '\x00275c'],
            ['ABC',        '\x414243']
        ];
    }
}