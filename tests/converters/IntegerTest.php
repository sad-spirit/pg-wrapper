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

use sad_spirit\pg_wrapper\exceptions\TypeConversionException,
    sad_spirit\pg_wrapper\converters\IntegerConverter;

/**
 * Unit test for integer type converter
 */
class IntegerTest extends TypeConverterTestCase
{
    public function setUp()
    {
        $this->converter = new IntegerConverter();
    }

    protected function valuesBoth()
    {
        return [
            [null, null],
            ['1', 1],
            ['2', 2],
            ['0', 0],
            ['-20', -20],
            ['9999999999', '9999999999']
        ];
    }

    protected function valuesFrom()
    {
        return [
            ['1.0', new TypeConversionException()],
            ['NaN', new TypeConversionException()]
        ];
    }

    protected function valuesTo()
    {
        return [
            [new TypeConversionException(), ''],
            [new TypeConversionException(), 'string'],
            [new TypeConversionException(), [1]],
        ];
    }
}
