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

namespace sad_spirit\pg_wrapper\converters;

use sad_spirit\pg_wrapper\exceptions\TypeConversionException,
    sad_spirit\pg_wrapper\types\Tid;

/**
 * Converter for tid (tuple identifier) type, representing physical location of a row within its table
 */
class TidConverter extends ContainerConverter
{
    /**
     * Converter for numbers within Tid
     * @var IntegerConverter
     */
    private $_integer;

    public function __construct()
    {
        $this->_integer = new IntegerConverter();
    }

    protected function parseInput($native, &$pos)
    {
        $this->expectChar($native, $pos, '(');

        $len = strcspn($native, ",)", $pos);
        $blockNumber = call_user_func(self::$substr, $native, $pos, $len);
        $pos += $len;

        $this->expectChar($native, $pos, ',');

        $len = strcspn($native, ",)", $pos);
        $offset = call_user_func(self::$substr, $native, $pos, $len);
        $pos += $len;

        $this->expectChar($native, $pos, ')');

        return new Tid($this->_integer->input($blockNumber), $this->_integer->input($offset));
    }

    protected function outputNotNull($value)
    {
        if (is_array($value)) {
            $value = Tid::createFromArray($value);
        } elseif (!($value instanceof Tid)) {
            throw TypeConversionException::unexpectedValue($this, 'output', 'instance of Tid or an array', $value);
        }
        /* @var $value Tid */
        return sprintf('(%d,%d)', $value->block, $value->tuple);
    }

    public function dimensions()
    {
        return 1;
    }
}
