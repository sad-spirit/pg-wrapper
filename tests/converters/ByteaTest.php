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

use sad_spirit\pg_wrapper\converters\ByteaConverter,
    sad_spirit\pg_wrapper\exceptions\TypeConversionException;

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
        $this->caster->useHexEncoding(true);
        $this->assertEquals($hexEncoded, $this->caster->output($value));
    }

    /**
     * Tests the escape encoding for bytea data
     *
     * We rely on pg_escape_bytea() and that may return hex encoding in some cases, looks like
     * this is not a bug:
     * https://bugs.php.net/bug.php?id=53127
     * http://www.postgresql.org/message-id/4B3501C7.4070901@beccati.com
     *
     * @dataProvider getValuesTo
     */
    public function testCastToEscape($value, $escapeEncoded, $hexEncoded)
    {
        $this->caster->useHexEncoding(false);
        $this->assertContains($this->caster->output($value), [$escapeEncoded, $hexEncoded]);
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
        return array_map(function($value) {
            return [$value[0], $value[2]];
        }, $this->getValuesTo());
    }

    public function getValuesTo()
    {
        return [
            [null,         null,           null],
            ['',           '',             '\x'],
            ["\000'\\",    "\\000'\\\\",   '\x00275c'],
            ['ABC',        'ABC',          '\x414243']
        ];
    }
}