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
 * @copyright 2014-2021 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
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
    protected function inputNotNull(string $native)
    {
        // Postgres stores numbers in JSON as values of "numeric" type, not "float"
        // To prevent loss of precision we should (try to) return these as strings
        $result = json_decode($native, true, 512, JSON_BIGINT_AS_STRING);

        if (null === $result && ($code = json_last_error())) {
            throw new TypeConversionException(sprintf('%s(): %s', __METHOD__, json_last_error_msg()));
        }

        return $result;
    }

    protected function outputNotNull($value): string
    {
        $result = json_encode($value);

        if (false === $result) {
            throw new TypeConversionException(sprintf('%s(): %s', __METHOD__, json_last_error_msg()));
        }

        return $result;
    }
}
