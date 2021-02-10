<?php

/**
 * Wrapper for PHP's pgsql extension providing conversion of complex DB types
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-wrapper/master/LICENSE
 *
 * @package   sad_spirit\pg_wrapper
 * @copyright 2014-2020 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
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
 * @property-read int|float|numeric-string|null $lower
 * @property-read int|float|numeric-string|null $upper
 */
final class NumericRange extends Range
{
    public function __construct(
        $lower = null,
        $upper = null,
        bool $lowerInclusive = true,
        bool $upperInclusive = false
    ) {
        foreach (['lower', 'upper'] as $bound) {
            if (null !== $$bound && !is_numeric($$bound)) {
                throw new InvalidArgumentException("NumericRange {$bound} bound should be numeric");
            }
        }
        if (null !== $lower && null !== $upper && floatval($upper) < floatval($lower)) {
            throw new InvalidArgumentException(
                "Range lower bound must be less than or equal to range upper bound"
            );
        }
        parent::__construct($lower, $upper, $lowerInclusive, $upperInclusive);
    }
}
