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
 * Class representing a multirange with DateTime bounds
 *
 * Used to convert PostgreSQL's tsmultirange, tstzmultirange, datemultirange types
 *
 * @extends MultiRange<DateTimeRange>
 */
final readonly class DateTimeMultiRange extends MultiRange
{
    public static function getItemClass(): string
    {
        return DateTimeRange::class;
    }
}
