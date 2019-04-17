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

declare(strict_types=1);

namespace sad_spirit\pg_wrapper\tests\converters;

use sad_spirit\pg_wrapper\{
    converters\NumericConverter,
    exceptions\TypeConversionException
};

/**
 * Unit test for numeric type converter
 */
class NumericTest extends TypeConverterTestCase
{
    public function setUp(): void
    {
        $this->converter = new NumericConverter();
    }
    
    public function testConvertNaN()
    {
        $this->assertTrue(is_nan($this->converter->input('NAN')));
        $this->assertEquals('NaN', $this->converter->output(floatval(NAN)));
    }

    protected function valuesBoth()
    {
        return [
            [null,   null],
            ['10',   '10'],
            ['-20',  '-20'],
            ['3.5',  '3.5']
        ];
    }

    protected function valuesFrom()
    {
        return [
            ['',     new TypeConversionException()],
            ['blah', new TypeConversionException()]
        ];
    }

    protected function valuesTo()
    {
        return [
            ['2.3',                         '2,3'],
            [new TypeConversionException(), ''],
            [new TypeConversionException(), 'fuck'],
            [new TypeConversionException(), []]
        ];
    }
}
