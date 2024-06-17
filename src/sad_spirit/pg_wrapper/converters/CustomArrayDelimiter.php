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

/**
 * This interface should be implemented by type converters for types having a non-comma array delimiter
 *
 * The only built-in type having an array delimiter that is not comma is 'box' (it uses semicolon).
 *
 * We don't bother checking 'typdelim' field from pg_type: a custom type needs a custom converter anyway,
 * it should implement this interface if array delimiter is not comma
 */
interface CustomArrayDelimiter
{
    /**
     * Returns the symbol used to separate items of this type inside string representation of an array
     *
     * @return string
     */
    public function getArrayDelimiter(): string;
}
