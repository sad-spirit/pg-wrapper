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

use sad_spirit\pg_wrapper\exceptions\InvalidArgumentException;

/**
 * Class representing a range with numeric bounds
 *
 * Used to convert PostgreSQL's int4range, int8range, numrange types
 *
 * @extends Range<int|float|numeric-string>
 */
final readonly class NumericRange extends Range
{
    public function __construct(
        mixed $lower = null,
        mixed $upper = null,
        bool $lowerInclusive = true,
        bool $upperInclusive = false,
        bool $empty = false
    ) {
        foreach (['lower', 'upper'] as $bound) {
            if (null !== ${$bound} && !\is_numeric(${$bound})) {
                throw new InvalidArgumentException("NumericRange {$bound} bound should be numeric");
            }
        }
        if (null !== $lower && null !== $upper) {
            if (\floatval($upper) < \floatval($lower)) {
                throw new InvalidArgumentException(
                    "Range lower bound must be less than or equal to range upper bound"
                );
            // comparing floats for equality is a bad idea, especially when we can lose precision,
            // so compare string representations
            } elseif ((string)$upper === (string)$lower && (!$lowerInclusive || !$upperInclusive)) {
                $empty = true;
            }
        }
        parent::__construct($lower, $upper, $lowerInclusive, $upperInclusive, $empty);
    }
}
