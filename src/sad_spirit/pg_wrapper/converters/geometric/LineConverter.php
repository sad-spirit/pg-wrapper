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

namespace sad_spirit\pg_wrapper\converters\geometric;

use sad_spirit\pg_wrapper\converters\ContainerConverter,
    sad_spirit\pg_wrapper\converters\FloatConverter,
    sad_spirit\pg_wrapper\exceptions\TypeConversionException,
    sad_spirit\pg_wrapper\types\Line;

/**
 * Converter for line type, represented by three coefficients of linear equation (PostgreSQL 9.4+)
 */
class LineConverter extends ContainerConverter
{
    /**
     * Converter for line's coefficients
     * @var FloatConverter
     */
    private $_float;

    public function __construct()
    {
        $this->_float = new FloatConverter();
    }

    protected function parseInput($native, &$pos)
    {
        $this->expectChar($native, $pos, '{');

        $len  = strcspn($native, ',}', $pos);
        $A    = call_user_func(self::$substr, $native, $pos, $len);
        $pos += $len;

        $this->expectChar($native, $pos, ',');

        $len  = strcspn($native, ',}', $pos);
        $B    = call_user_func(self::$substr, $native, $pos, $len);
        $pos += $len;

        $this->expectChar($native, $pos, ',');

        $len  = strcspn($native, ',}', $pos);
        $C    = call_user_func(self::$substr, $native, $pos, $len);
        $pos += $len;

        $this->expectChar($native, $pos, '}');

        return new Line($this->_float->input($A), $this->_float->input($B), $this->_float->input($C));
    }

    protected function outputNotNull($value)
    {
        if (is_array($value)) {
            $value = Line::createFromArray($value);
        } elseif (!($value instanceof Line)) {
            throw TypeConversionException::unexpectedValue($this, 'output', 'instance of Line or an array', $value);
        }
        return '{' . $this->_float->output($value->A) . ',' . $this->_float->output($value->B)
               . ',' . $this->_float->output($value->C) . '}';
    }

    public function dimensions()
    {
        return 1;
    }
}