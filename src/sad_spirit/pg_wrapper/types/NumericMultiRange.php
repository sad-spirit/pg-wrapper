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
 * Class representing a multirange with numeric bounds
 *
 * Used to convert PostgreSQL's int4multirange, int8multirange, nummultirange types
 *
 * @extends MultiRange<NumericRange>
 */
final readonly class NumericMultiRange extends MultiRange
{
    public static function getItemClass(): string
    {
        return NumericRange::class;
    }
}
