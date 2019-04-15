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

use sad_spirit\pg_wrapper\converters\JSONConverter,
    sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Unit test for JSON (and JSONB) type converter
 */
class JSONTest extends TypeConverterTestCase
{
    public function setUp()
    {
        $this->converter = new JSONConverter();
    }

    public function testJSONBigintAsString()
    {
        $this->assertSame(
            array('largenum' => '123456789012345678901234567890'),
            $this->converter->input('{"largenum":123456789012345678901234567890}')
        );
    }

    public function testInvalidUTF8Sequence()
    {
        $this->setExpectedException('\sad_spirit\pg_wrapper\exceptions\TypeConversionException');
        $this->converter->output("\xB1\x31");
    }

    protected function valuesBoth()
    {
        return array(
            array(null,                         null),
            array('false',                      false),
            array('"\u0442\u0435\u0441\u0442"', 'тест'),
            array('{"foo":"bar"}',              array('foo' => 'bar'))
        );
    }

    protected function valuesFrom()
    {
        return array(
            array('"тест"',       'тест'),
            array('[1}',          new TypeConversionException()),
            array("\"\xB1\x31\"", new TypeConversionException())
        );
    }

    protected function valuesTo()
    {
        $foo = new \stdClass();
        $foo->bar = $foo;

        return array(
            array(new TypeConversionException(), $foo),
            array(new TypeConversionException(), fopen(__DIR__ . '/TypeConverterTestCase.php', 'rb')),
            array(new TypeConversionException(), array(NAN))
        );
    }

}