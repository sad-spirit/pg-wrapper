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
 * Converter for oid8 type added in Postgres 19
 *
 * This type is essentially an unsigned 64-bit integer, thus it can't be handled by an existing IntegerConverter:
 * the value may overflow even on 64-bit PHP builds.
 *
 * @since 3.4.0
 */
class OID8Converter extends IntegerConverter
{
    protected function inputNotNull(string $native): int|string
    {
        $native = \trim($native, self::WHITESPACE);
        if (!\ctype_digit($native)) {
            throw TypeConversionException::unexpectedValue($this, 'input', 'unsigned integer value', $native);
        } else {
            // convert to int and check for overflow, needed even on 64-bit builds
            $int = (int)$native;
            return $native === \strval($int) ? $int : $native;
        }
    }
}