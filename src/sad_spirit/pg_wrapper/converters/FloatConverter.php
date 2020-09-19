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
 * @copyright 2014-2020 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

declare(strict_types=1);

namespace sad_spirit\pg_wrapper\converters;

use sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Converter for float type, handles corner cases like NaN and Infinity
 */
class FloatConverter extends BaseConverter
{
    protected function inputNotNull(string $native)
    {
        $native = trim($native);
        if (is_numeric($native)) {
            return (double)$native;

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
        if (is_scalar($value)) {
            if (!is_float($value)) {
                $value = str_replace(',', '.', (string)$value);
            } elseif (is_nan($value)) {
                return 'NaN';
            } elseif (is_infinite($value)) {
                return $value > 0 ? 'Infinity' : '-Infinity';
            } else {
                $value = (string)$value;
            }
        }

        if (!is_scalar($value) || !is_numeric($value)) {
            throw TypeConversionException::unexpectedValue($this, 'output', 'numeric value', $value);
        }
        return $value;
    }
}
