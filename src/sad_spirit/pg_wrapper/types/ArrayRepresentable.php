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

namespace sad_spirit\pg_wrapper\types;

/**
 * Interface for types that can be created from arrays
 */
interface ArrayRepresentable
{
    /**
     * Creates an object based on the contents of the array
     */
    public static function createFromArray(array $input): static;
}
