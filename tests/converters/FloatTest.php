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
 * @copyright 2014-2020 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

declare(strict_types=1);

namespace sad_spirit\pg_wrapper\tests\converters;

use sad_spirit\pg_wrapper\{
    converters\FloatConverter,
    exceptions\TypeConversionException
};

/**
 * Unit test for float type converter
 */
class FloatTest extends TypeConverterTestCase
{
    public function setUp(): void
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
        return [
            [null, null],
            ['1', 1],
            ['2', 2],
            ['2.3', 2.3],
            ['0', 0],
            ['-20', -20],
        ];
    }

    protected function valuesFrom()
    {
        return [
            ['1', 1.0],
            ['1', '1'],
            ['1', '1.0'],
            ['2', '2'],
            ['2.3', '2.3'],
            ['0', '0'],
            ['-20', '-20'],
        ];
    }

    protected function valuesTo()
    {
        return [
            ['2.3', '2,3'],
            [new TypeConversionException(), ''],
            [new TypeConversionException(), 'string'],
            [new TypeConversionException(), [1]],
        ];
    }
}
