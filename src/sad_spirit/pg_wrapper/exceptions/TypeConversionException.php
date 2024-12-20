<?php

/*
 * This file is part of sad_spirit/pg_wrapper:
 * converter of complex PostgreSQL types and an OO wrapper for PHP's pgsql extension.
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
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
     */
    public static function parsingFailed(TypeConverter $converter, string $expected, string $given, int $position): self
    {
        return new self(
            $converter::class . '::input(): error parsing database value: unexpected input '
            . "'" . \substr_replace($given, ">>HERE>>", $position, 0) . "' at position {$position}"
            . ", expecting {$expected}"
        );
    }

    /**
     * Thrown when an unexpected value was received (either for input or output)
     */
    public static function unexpectedValue(
        TypeConverter $converter,
        string $method,
        string $expected,
        mixed $given
    ): self {
        return new self(
            $converter::class . '::' . $method . '(): unexpected '
            . self::stringify($given) . ', expecting ' . $expected
        );
    }

    /**
     * Thrown in Connection::guessOutputFormat() when guessing fails
     */
    public static function guessFailed(mixed $value): self
    {
        return new self(
            'Failed to deduce a proper type converter for '
            . self::stringify($value) . ', specify an explicit native type'
        );
    }
}
