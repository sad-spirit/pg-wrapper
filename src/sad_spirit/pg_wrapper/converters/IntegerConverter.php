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
 * Converter for integer types (int2, int4, int8)
 */
class IntegerConverter extends BaseNumericConverter
{
    protected function inputNotNull(string $native): int|string
    {
        $native = \trim($native);
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
