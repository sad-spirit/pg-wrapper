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
 * Converter for json / jsonb PostgreSQL types
 *
 * This will only work correctly with non-ASCII symbols if both PHP encoding and
 * database encoding is UTF-8
 */
class JSONConverter extends BaseConverter
{
    protected function inputNotNull(string $native): mixed
    {
        // Postgres stores numbers in JSON as values of "numeric" type, not "float"
        // To prevent loss of precision we should (try to) return these as strings
        $result = \json_decode($native, true, 512, \JSON_BIGINT_AS_STRING);

        if (null === $result && \JSON_ERROR_NONE !== \json_last_error()) {
            throw new TypeConversionException(\sprintf('%s(): %s', __METHOD__, \json_last_error_msg()));
        }

        return $result;
    }

    protected function outputNotNull(mixed $value): string
    {
        $result = \json_encode($value);

        if (false === $result) {
            throw new TypeConversionException(\sprintf('%s(): %s', __METHOD__, \json_last_error_msg()));
        }

        return $result;
    }
}
