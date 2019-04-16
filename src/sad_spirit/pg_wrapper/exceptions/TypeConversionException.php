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

namespace sad_spirit\pg_wrapper\exceptions;

use sad_spirit\pg_wrapper\{
    Exception,
    TypeConverter
};

/**
 * Exception thrown when conversion of value from/to database representation fails
 */
class TypeConversionException extends \DomainException implements Exception
{
    use Stringifier;

    /**
     * Thrown when parsing the database value failed (and we know exactly where)
     *
     * @param TypeConverter $converter
     * @param string        $expected
     * @param string        $given
     * @param int           $position
     * @return TypeConversionException
     */
    public static function parsingFailed(TypeConverter $converter, string $expected, string $given, int $position): self
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
    public static function unexpectedValue(TypeConverter $converter, string $method, string $expected, $given): self
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
    public static function guessFailed($value): self
    {
        return new self(
            'Failed to deduce a proper type converter for '
            . self::stringify($value) . ', specify an explicit native type'
        );
    }
}