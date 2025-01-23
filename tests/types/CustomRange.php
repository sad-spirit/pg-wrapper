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

namespace sad_spirit\pg_wrapper\tests\types;

use sad_spirit\pg_wrapper\exceptions\InvalidArgumentException;
use sad_spirit\pg_wrapper\types\Range;

/**
 * Custom range implementation for additional RangeConverter test
 *
 * Converts string range bounds to uppercase and performs standard checks for bounds
 *
 * @extends Range<string>
 */
readonly class CustomRange extends Range
{
    public function __construct(
        mixed $lower = null,
        mixed $upper = null,
        bool $lowerInclusive = true,
        bool $upperInclusive = false,
        bool $empty = false
    ) {
        $lower = null === $lower ? $lower : \strtoupper((string)$lower);
        $upper = null === $upper ? $upper : \strtoupper((string)$upper);
        if (null !== $lower && null !== $upper) {
            if ($lower > $upper) {
                throw new InvalidArgumentException(
                    "Range lower bound must be less than or equal to range upper bound"
                );
            } elseif ($upper === $lower && (!$lowerInclusive || !$upperInclusive)) {
                $empty = true;
            }
        }
        parent::__construct($lower, $upper, $lowerInclusive, $upperInclusive, $empty);
    }
}
