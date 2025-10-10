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

namespace sad_spirit\pg_wrapper\converters;

use sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Converter for bytea (binary string) type
 *
 * Converting value from database representation handles both old 'escape' format
 * via pg_unescape_bytea() and newer 'hex' format used by Postgres 9+.
 * Converting to database representation always uses the latter
 */
class ByteaConverter extends BaseConverter
{
    protected function inputNotNull(string $native): string
    {
        if (!\str_starts_with($native, '\x')) {
            return \pg_unescape_bytea($native);

        } else {
            // http://www.postgresql.org/docs/current/datatype-binary.html says:
            // The "hex" format encodes binary data as 2 hexadecimal digits per byte, most significant nibble first.
            // The entire string is preceded by the sequence \x (to distinguish it from the escape format).
            // The hexadecimal digits can be either upper or lower case, and whitespace is permitted between digit
            // pairs (but not within a digit pair nor in the starting \x sequence).
            $warning = '';
            $result  = '';
            $start   = 2;
            $length  = \strlen($native);
            while ($start < $length) {
                $start += \strspn($native, self::WHITESPACE, $start);
                $hexes  = \strcspn($native, self::WHITESPACE, $start);
                if ($hexes > 0) {
                    if ($hexes % 2) {
                        throw new TypeConversionException(\sprintf(
                            '%s(): expecting even number of hex digits, %d hex digit(s) found',
                            __METHOD__,
                            $hexes
                        ));
                    }

                    // pack() throws a warning, but returns a string nonetheless, so use warnings handler
                    \set_error_handler(static function ($errno, $errstr) use (&$warning): true {
                        $warning = $errstr;
                        return true;
                    }, \E_WARNING);
                    $result .= \pack('H*', \substr($native, $start, $hexes));
                    $start  += $hexes;
                    \restore_error_handler();

                    if ($warning) {
                        throw new TypeConversionException(\sprintf('%s(): %s', __METHOD__, $warning));
                    }
                }
            }
            return $result;
        }
    }

    /**
     * Returns the encoded binary string
     *
     * This always uses 'hex' encoding
     *
     * @param mixed $value
     * @return string
     * @throws TypeConversionException if $value is not a string
     */
    protected function outputNotNull(mixed $value): string
    {
        if (!\is_string($value)) {
            throw TypeConversionException::unexpectedValue($this, 'output', 'string', $value);
        } elseif (false === $encoded = \unpack('H*', $value)) {
            // Unlikely to happen
            throw new TypeConversionException("Failed to encode binary string");
        }

        return '\x' . $encoded[1];
    }
}
