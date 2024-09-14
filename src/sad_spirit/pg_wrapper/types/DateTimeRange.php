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
 * Class representing a range with DateTime bounds
 *
 * Used to convert PostgreSQL's tsrange, tstzrange, daterange types
 *
 * @extends Range<\DateTimeImmutable>
 */
final readonly class DateTimeRange extends Range
{
    public function __construct(
        mixed $lower = null,
        mixed $upper = null,
        bool $lowerInclusive = true,
        bool $upperInclusive = false,
        bool $empty = false
    ) {
        // As we can't add typehints due to interface, check bounds in constructor
        foreach (['lower', 'upper'] as $bound) {
            if (null !== $$bound && !$$bound instanceof \DateTimeInterface) {
                throw new InvalidArgumentException(
                    "DateTimeRange {$bound} bound should be an instance of DateTimeInterface"
                );
            }
            if ($$bound instanceof \DateTime) {
                $$bound = \DateTimeImmutable::createFromMutable($$bound);
            }
        }
        if (null !== $lower && null !== $upper) {
            if ($lower > $upper) {
                throw new InvalidArgumentException(
                    "Range lower bound must be less than or equal to range upper bound"
                );
            } elseif ($lower == $upper && (!$lowerInclusive || !$upperInclusive)) {
                $empty = true;
            }
        }

        parent::__construct($lower, $upper, $lowerInclusive, $upperInclusive, $empty);
    }

    protected static function convertBound(mixed $bound): ?\DateTimeImmutable
    {
        if (null === $bound || $bound instanceof \DateTimeImmutable) {
            return $bound;
        } elseif ($bound instanceof \DateTime) {
            return \DateTimeImmutable::createFromMutable($bound);
        } elseif (\is_array($bound)) {
            return \DateTimeImmutable::__set_state($bound);
        }
        throw InvalidArgumentException::unexpectedType(
            __METHOD__,
            "a null or an instance of \DateTimeInterface (possibly JSON-encoded)",
            $bound
        );
    }
}
