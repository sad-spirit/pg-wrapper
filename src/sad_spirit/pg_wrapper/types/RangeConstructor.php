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
 * Fixes signature of Range's __construct() method so that "new static(...)" will not break
 */
interface RangeConstructor
{
    /**
     * All Range subclasses should have the same constructor signature
     *
     * @param mixed|null $lower          Range lower bound (type checks should be done in child constructor)
     * @param mixed|null $upper          Range upper bound (type checks should be done in child constructor)
     * @param bool       $lowerInclusive Whether lower bound is inclusive
     * @param bool       $upperInclusive Whether upper bound is inclusive
     * @param bool       $empty          If true, an empty range is created (all other parameters are ignored)
     */
    public function __construct(
        mixed $lower = null,
        mixed $upper = null,
        bool $lowerInclusive = true,
        bool $upperInclusive = false,
        bool $empty = false
    );
}
