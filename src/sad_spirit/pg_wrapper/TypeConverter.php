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

namespace sad_spirit\pg_wrapper;

/**
 * Interface for type converters to and from native DB format
 */
interface TypeConverter
{
    /**
     * Converts PHP variable to a native format
     *
     * @throws exceptions\TypeConversionException if converter doesn't know how to process $value
     */
    public function output(mixed $value): ?string;

    /**
     * Parses a native value into PHP variable
     *
     * @throws exceptions\TypeConversionException if converter cannot parse the incoming string
     */
    public function input(?string $native): mixed;

    /**
     * Number of array dimensions for PHP variable
     *
     * Returns zero if variable is scalar. This method is mostly needed for
     * correct arrays conversion.
     */
    public function dimensions(): int;
}
