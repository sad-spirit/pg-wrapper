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
 * @copyright 2014-2023 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

declare(strict_types=1);

namespace sad_spirit\pg_wrapper\types;

/**
 * Wrapper around PHP's built-in DateInterval class
 *
 * Adds a method to return the string representation of interval.
 */
class DateInterval extends \DateInterval
{
    /**
     * Returns the value of DateInterval object as an ISO 8601 time interval string
     *
     * This string will not necessarily work with DateInterval's constructor
     * as that cannot handle negative numbers.
     * Mostly intended for sending to Postgres as a value of interval type.
     *
     * @param  \DateInterval $interval
     * @return string
     */
    public static function formatAsISO8601(\DateInterval $interval): string
    {
        $string = 'P';
        $mult   = $interval->invert ? -1 : 1;
        foreach (['y' => 'Y', 'm' => 'M', 'd' => 'D'] as $key => $char) {
            if (0 !== $interval->{$key}) {
                $string .= sprintf('%d%s', $interval->{$key} * $mult, $char);
            }
        }
        if (0 !== $interval->h || 0 !== $interval->i || 0 !== $interval->s || 0.0 !== $interval->f) {
            $string .= 'T';
            foreach (['h' => 'H', 'i' => 'M'] as $key => $char) {
                if (0 !== $interval->{$key}) {
                    $string .= sprintf('%d%s', $interval->{$key} * $mult, $char);
                }
            }
            if (0 !== $interval->s || 0.0 !== $interval->f) {
                if (0.0 === $interval->f) {
                    $string .= sprintf('%d%s', $interval->s * $mult, 'S');
                } else {
                    $string .= rtrim(sprintf('%.6f', ($interval->s + $interval->f) * $mult), '0');
                    $string .= 'S';
                }
            }
        }

        // prevent returning an empty string
        return 'P' === $string ? 'PT0S' : $string;
    }

    public function __toString()
    {
        return self::formatAsISO8601($this);
    }
}
