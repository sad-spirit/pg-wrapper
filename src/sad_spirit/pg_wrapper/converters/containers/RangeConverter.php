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
 * @copyright 2014 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

namespace sad_spirit\pg_wrapper\converters\containers;

use sad_spirit\pg_wrapper\converters\ContainerConverter,
    sad_spirit\pg_wrapper\converters\datetime\BaseDateTimeConverter,
    sad_spirit\pg_wrapper\converters\FloatConverter,
    sad_spirit\pg_wrapper\converters\IntegerConverter,
    sad_spirit\pg_wrapper\converters\NumericConverter,
    sad_spirit\pg_wrapper\TypeConverter,
    sad_spirit\pg_wrapper\types\Range,
    sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Converter for range types of PostgreSQL 9.1+
 */
class RangeConverter extends ContainerConverter
{
    /**
     * Converter for the base type of the range
     * @var TypeConverter
     */
    private $_subtype;

    /**
     * input() will return instances of this class
     * @var string
     */
    protected $resultClass = '\sad_spirit\pg_wrapper\types\Range';

    /**
     * Constructor, sets converter for the
     *
     * @param TypeConverter $subtype
     */
    public function __construct(TypeConverter $subtype)
    {
        $this->_subtype = $subtype;

        if ($subtype instanceof FloatConverter || $subtype instanceof NumericConverter
            || $subtype instanceof IntegerConverter
        ) {
            $this->resultClass = '\sad_spirit\pg_wrapper\types\NumericRange';
        } elseif ($subtype instanceof BaseDateTimeConverter) {
            $this->resultClass = '\sad_spirit\pg_wrapper\types\DateTimeRange';
        }
    }

    /**
     * Reads a range bound (upper or lower) from input
     *
     * @param string $string
     * @param int    $pos
     * @param string $delimiter
     *
     * @return null|string
     * @throws TypeConversionException
     */
    private function _readRangeBound($string, &$pos, $delimiter)
    {
        $bound  = null;
        $quoted = preg_quote($delimiter);
        while (!strspn($string, $delimiter, $pos)) {
            if ('"' === $string[$pos]) {
                if (!preg_match('/"((?>[^"\\\\]+|\\\\.|"")*)"/As', $string, $m, 0, $pos)) {
                    throw TypeConversionException::parsingFailed($this, 'quoted string', $string, $pos);
                }
                $pos   += call_user_func(self::$strlen, $m[0]);
                $bound .= strtr($m[1], array('\\\\' => '\\', '\\"' => '"', '""' => '"'));

            } else {
                preg_match("/(?>[^\"\\\\{$quoted}]+|\\\\.)+/As", $string, $m, 0, $pos);
                $pos   += call_user_func(self::$strlen, $m[0]);
                $bound .= stripcslashes($m[0]);
            }
        }

        return $bound;
    }

    /**
     * Parses a native value into PHP variable from given position
     *
     * @param string $native
     * @param int    $pos
     *
     * @return Range
     * @throws TypeConversionException
     */
    protected function parseInput($native, &$pos)
    {
        $char = $this->nextChar($native, $pos);
        if (('e' === $char || 'E' === $char) && preg_match('/empty/Ai', $native, $m, 0, $pos)) {
            $pos += 5;
            return call_user_func(array($this->resultClass, 'createEmpty'));
        }

        if ('(' === $char || '[' === $char) {
            $pos++;
            $lowerInclusive = '[' === $char;

        } else {
            throw TypeConversionException::parsingFailed($this, '[ or (', $native, $pos);
        }

        $lower = $this->_readRangeBound($native, $pos, ',)]');
        $this->expectChar($native, $pos, ',');
        $upper = $this->_readRangeBound($native, $pos, ',])');

        if (']' === $native[$pos]) {
            $upperInclusive = true;
            $pos++;

        } elseif (')' === $native[$pos]) {
            $upperInclusive = false;
            $pos++;

        } else {
            throw TypeConversionException::parsingFailed($this, '] or )', $native, $pos);
        }

        return new $this->resultClass(
            $this->_subtype->input($lower), $this->_subtype->input($upper),
            $lowerInclusive, $upperInclusive
        );
    }

    protected function outputNotNull($value)
    {
        if (is_array($value)) {
            $value = call_user_func(array($this->resultClass, 'createFromArray'), $value);
        } elseif (!($value instanceof Range)) {
            throw TypeConversionException::unexpectedValue($this, 'output', 'instance of Range or an array', $value);
        }
        /* @var $value Range */
        if ($value->empty) {
            return 'empty';
        }
        return ($value->lowerInclusive ? '[' : '(')
               . (null === $value->lower
                  ? '' : '"' . addcslashes($this->_subtype->output($value->lower), "\"\\") . '"')
               . ','
               . (null === $value->upper
                  ? '' : '"' . addcslashes($this->_subtype->output($value->upper), "\"\\") . '"')
               . ($value->upperInclusive ? ']' : ')');
    }
}