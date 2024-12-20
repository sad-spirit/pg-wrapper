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
 * Converter for arbitrary precision numbers, keeps them as strings, handles NaN and Infinity
 */
class NumericConverter extends BaseNumericConverter
{
    protected function inputNotNull(string $native): string|float
    {
        $native = \trim($native, self::WHITESPACE);
        if (\is_numeric($native)) {
            return $native;

        } elseif (0 === \strcasecmp($native, 'NaN')) {
            return \NAN;

        } elseif (0 === \strcasecmp($native, 'Infinity')) {
            return \INF;

        } elseif (0 === \strcasecmp($native, '-Infinity')) {
            return -\INF;

        } else {
            throw TypeConversionException::unexpectedValue($this, 'input', 'numeric value', $native);
        }
    }

    protected function outputNotNull(mixed $value): string
    {
        if (\is_float($value)) {
            if (\is_nan($value)) {
                return 'NaN';
            } elseif (\is_infinite($value)) {
                return $value > 0 ? 'Infinity' : '-Infinity';
            } else {
                return \strtr((string)$value, ',', '.');
            }
        }
        if (\is_string($value)) {
            $value = \strtr($value, ',', '.');
        }

        if (\is_scalar($value) && \is_numeric($value)) {
            return (string)$value;
        } elseif (
            \is_string($value)
            && $this->allowNonDecimalLiteralsAndUnderscores()
            && (
                \preg_match(self::REGEXP_INTEGER, $value)
                || \preg_match(self::REGEXP_REAL, $value)
            )
        ) {
            return $value;
        }

        throw TypeConversionException::unexpectedValue($this, 'output', 'numeric value', $value);
    }
}
