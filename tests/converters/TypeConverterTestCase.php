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

use sad_spirit\pg_wrapper\TypeConverter;

/**
 * Base class for type converter tests
 */
abstract class TypeConverterTestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * @var TypeConverter
     */
    protected $converter;

    /**
     * @dataProvider getValuesFrom
     */
    public function testCastFrom($native, $value)
    {
        if ($value instanceof \Exception) {
            $this->setExpectedException(get_class($value));
            $this->converter->input($native);
        } else {
            $this->assertEquals($value, $this->converter->input($native));
        }
    }

    /**
     * @dataProvider getValuesTo
     */
    public function testCastTo($native, $value)
    {
        if ($native instanceof \Exception) {
            $this->setExpectedException(get_class($native));
            $this->converter->output($value);
        } else {
            $this->assertEquals($native, $this->converter->output($value));
        }
    }

    public function getValuesFrom()
    {
        return array_merge($this->valuesBoth(), $this->valuesFrom());
    }

    public function getValuesTo()
    {
        return array_merge($this->valuesBoth(), $this->valuesTo());
    }

    abstract protected function valuesBoth();
    abstract protected function valuesFrom();
    abstract protected function valuesTo();
}
