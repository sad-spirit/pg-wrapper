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
 * Converter for integer types (int2, int4, int8)
 */
class IntegerConverter extends BaseNumericConverter
{
    protected function inputNotNull(string $native): int|string
    {
        $native = \trim($native, self::WHITESPACE);
        if (!\ctype_digit($native) && !\preg_match('/^-\d+$/', $native)) {
            throw TypeConversionException::unexpectedValue($this, 'input', 'integer value', $native);

        } elseif (\PHP_INT_SIZE >= 8) {
            // 64-bit system: any Postgres integer type may be represented by PHP integer
            return (int)$native;

        } else {
            // convert to int and check for overflow
            $int = (int)$native;
            return $native === \strval($int) ? $int : $native;
        }
    }

    protected function outputNotNull(mixed $value): string
    {
        if (\is_numeric($value)) {
            return (string)$value;
        } elseif (
            \is_string($value)
            && $this->allowNonDecimalLiteralsAndUnderscores()
            && \preg_match(self::REGEXP_INTEGER, $value)
        ) {
            return $value;
        }
        throw TypeConversionException::unexpectedValue($this, 'output', 'numeric value', $value);
    }
}
