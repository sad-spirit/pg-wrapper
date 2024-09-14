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
