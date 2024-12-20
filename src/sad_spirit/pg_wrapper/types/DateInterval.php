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
 * Wrapper around PHP's built-in DateInterval class
 *
 * Adds a method to return the string representation of interval.
 */
class DateInterval extends \DateInterval implements \Stringable
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
                $string .= \sprintf('%d%s', $interval->{$key} * $mult, $char);
            }
        }
        if (0 !== $interval->h || 0 !== $interval->i || 0 !== $interval->s || 0.0 !== $interval->f) {
            $string .= 'T';
            foreach (['h' => 'H', 'i' => 'M'] as $key => $char) {
                if (0 !== $interval->{$key}) {
                    $string .= \sprintf('%d%s', $interval->{$key} * $mult, $char);
                }
            }
            if (0 !== $interval->s || 0.0 !== $interval->f) {
                if (0.0 === $interval->f) {
                    $string .= \sprintf('%d%s', $interval->s * $mult, 'S');
                } else {
                    $string .= \rtrim(\sprintf('%.6f', ($interval->s + $interval->f) * $mult), '0');
                    $string .= 'S';
                }
            }
        }

        // prevent returning an empty string
        return 'P' === $string ? 'PT0S' : $string;
    }

    public function __toString(): string
    {
        return self::formatAsISO8601($this);
    }
}
