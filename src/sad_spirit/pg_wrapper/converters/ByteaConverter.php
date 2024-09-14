<?php

/**
 * Converter of complex PostgreSQL types and an OO wrapper for PHP's pgsql extension
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-wrapper/master/LICENSE
 *
 * @package   sad_spirit\pg_wrapper
 * @copyright 2014-2024 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
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
            // http://www.postgresql.org/docs/current/interactive/datatype-binary.html says:
            // The "hex" format encodes binary data as 2 hexadecimal digits per byte, most significant nibble first.
            // The entire string is preceded by the sequence \x (to distinguish it from the escape format).
            // The hexadecimal digits can be either upper or lower case, and whitespace is permitted between digit
            // pairs (but not within a digit pair nor in the starting \x sequence).
            $warning = '';
            $result  = '';
            $start   = 2;
            $length  = \strlen($native);
            while ($start < $length) {
                $start += \strspn($native, " \n\r\t", $start);
                $hexes  = \strcspn($native, " \n\r\t", $start);
                if ($hexes > 0) {
                    if ($hexes % 2) {
                        throw new TypeConversionException(\sprintf(
                            '%s(): expecting even number of hex digits, %d hex digit(s) found',
                            __METHOD__,
                            $hexes
                        ));
                    }

                    // pack() throws a warning, but returns a string nonetheless, so use warnings handler
                    \set_error_handler(function ($errno, $errstr) use (&$warning): true {
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
        }

        [, $encoded] = \unpack('H*', $value);
        return '\x' . $encoded;
    }
}
