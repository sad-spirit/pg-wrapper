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
 * @copyright 2014-2023 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

declare(strict_types=1);

namespace sad_spirit\pg_wrapper\converters;

use sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Converter for arbitrary precision numbers, keeps them as strings, handles NaN and Infinity
 */
class NumericConverter extends BaseConverter
{
    protected function inputNotNull(string $native)
    {
        $native = trim($native);
        if (is_numeric($native)) {
            return $native;

        } elseif (0 === strcasecmp($native, 'NaN')) {
            return NAN;

        } elseif (0 === strcasecmp($native, 'Infinity')) {
            return INF;

        } elseif (0 === strcasecmp($native, '-Infinity')) {
            return -INF;

        } else {
            throw TypeConversionException::unexpectedValue($this, 'input', 'numeric value', $native);
        }
    }

    protected function outputNotNull($value): string
    {
        if (is_float($value)) {
            if (is_nan($value)) {
                return 'NaN';
            } elseif (is_infinite($value)) {
                return $value > 0 ? 'Infinity' : '-Infinity';
            } else {
                return strtr((string)$value, ',', '.');
            }
        }
        if (is_string($value)) {
            $value = strtr($value, ',', '.');
        }

        if (!is_scalar($value) || !is_numeric($value)) {
            throw TypeConversionException::unexpectedValue($this, 'output', 'numeric value', $value);
        }
        return (string)$value;
    }
}
