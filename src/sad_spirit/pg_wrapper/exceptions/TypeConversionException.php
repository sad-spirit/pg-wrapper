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

namespace sad_spirit\pg_wrapper\exceptions;

use sad_spirit\pg_wrapper\Exception,
    sad_spirit\pg_wrapper\TypeConverter;

/**
 * Exception thrown when conversion of value from/to database representation failed
 */
class TypeConversionException extends \DomainException implements Exception
{
    /**
     * Thrown when parsing the database value failed (and we know exactly where)
     *
     * @param TypeConverter $converter
     * @param string        $expected
     * @param string        $given
     * @param int           $position
     * @return TypeConversionException
     */
    public static function parsingFailed(TypeConverter $converter, $expected, $given, $position)
    {
        return new self(
            get_class($converter) . '::input(): error parsing database value: unexpected input '
            . "'" . substr_replace($given, ">>HERE>>", $position, 0) . "' at position {$position}"
            . ", expecting {$expected}"
        );
    }

    /**
     * Thrown when an unexpected value was received (either for input or output)
     *
     * @param TypeConverter $converter
     * @param string        $method
     * @param string        $expected
     * @param mixed         $given
     * @return TypeConversionException
     */
    public static function unexpectedValue(TypeConverter $converter, $method, $expected, $given)
    {
        return new self(
            get_class($converter) . '::' . $method . '(): unexpected '
            . self::stringify($given) . ', expecting ' . $expected
        );
    }

    /**
     * Thrown in Connection::guessOutputFormat() when guessing fails
     *
     * @param mixed $value
     * @return TypeConversionException
     */
    public static function guessFailed($value)
    {
        return new self(
            'Failed to deduce a proper type converter for '
            . self::stringify($value) . ', specify an explicit native type'
        );
    }

    /**
     * Returns a string representation of $value for exception message
     *
     * @param mixed $value
     * @return string
     */
    protected static function stringify($value)
    {
        if (is_object($value)) {
            return 'Object(' . get_class($value) . ')';

        } elseif (is_array($value)) {
            $strings = array();
            foreach ($value as $k => $v) {
                $strings[] = sprintf('%s => %s', $k, self::stringify($v));
            }
            return 'Array(' . implode(', ', $strings) . ')';

        } elseif (is_resource($value)) {
            return 'Resource (' . get_resource_type($value) . ')';

        } elseif (is_null($value)) {
            return 'null';

        } elseif (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return "'" . (string)$value . "'";
    }
}