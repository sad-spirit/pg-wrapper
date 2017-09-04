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
 * @copyright 2014-2017 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

namespace sad_spirit\pg_wrapper\types;

use sad_spirit\pg_wrapper\exceptions\InvalidArgumentException,
    DateTime;

/**
 * Class representing a range with DateTime bounds
 *
 * Used to convert PostgreSQL's tsrange, tstzrange, daterange types
 */
class DateTimeRange extends Range
{
    public function __construct(
        DateTime $lower = null, DateTime $upper = null, $lowerInclusive = true, $upperInclusive = false
    ) {
        if (null !== $lower && null !== $upper && $lower > $upper) {
            throw new InvalidArgumentException(
                "Range lower bound must be less than or equal to range upper bound"
            );
        }
        parent::__construct($lower, $upper, $lowerInclusive, $upperInclusive);
    }

    public function __set($name, $value)
    {
        if (('upper' === $name || 'lower' === $name) && null !== $value) {
            if (!($value instanceof DateTime)) {
                throw new InvalidArgumentException(
                    "DateTimeRange {$name} bound should be an instance of DateTime"
                );
            }
            if ('upper' === $name && null !== $this->lower && $this->lower > $value
                || 'lower' === $name && null !== $this->upper && $this->upper < $value
            ) {
                throw new InvalidArgumentException(
                    "Range lower bound must be less than or equal to range upper bound"
                );
            }
        }
        parent::__set($name, $value);
    }
}
