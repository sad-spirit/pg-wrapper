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

/**
 * Converter for float type, handles corner cases like NaN and Infinity
 */
class FloatConverter extends NumericConverter
{
    protected function inputNotNull(string $native): string|float
    {
        $result = parent::inputNotNull($native);
        return \is_string($result) ? (float)$result : $result;
    }
}
