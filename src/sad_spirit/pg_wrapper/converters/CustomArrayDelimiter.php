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
 * This interface should be implemented by type converters for types having a non-comma array delimiter
 *
 * The only built-in type having an array delimiter that is not comma is `'box'` (it uses semicolon).
 *
 * We don't bother checking `'typdelim'` field from `pg_type`: a custom type needs a custom converter anyway,
 * it should implement this interface if array delimiter is not comma
 */
interface CustomArrayDelimiter
{
    /**
     * Returns the symbol used to separate items of this type inside string representation of an array
     */
    public function getArrayDelimiter(): string;
}
