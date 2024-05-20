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

namespace sad_spirit\pg_wrapper;

/**
 * Interface for type converters to and from native DB format
 */
interface TypeConverter
{
    /**
     * Converts PHP variable to a native format
     *
     * @param mixed $value
     * @return string|null
     * @throws exceptions\TypeConversionException if converter doesn't know how to process $value
     */
    public function output($value): ?string;

    /**
     * Parses a native value into PHP variable
     *
     * @param string|null $native
     * @return mixed
     * @throws exceptions\TypeConversionException if converter cannot parse the incoming string
     */
    public function input(?string $native);


    /**
     * Number of array dimensions for PHP variable
     *
     * Returns zero if variable is scalar. This method is mostly needed for
     * correct arrays conversion.
     *
     * @return int
     */
    public function dimensions(): int;
}
