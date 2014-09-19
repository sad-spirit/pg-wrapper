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
 * @copyright 2014 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

namespace sad_spirit\pg_wrapper\types;

use DateInterval as NativeDateInterval;

/**
 * Wrapper around PHP's built-in DateInterval class
 *
 * Adds support for fractional seconds and defines a method to return a string
 * representaion of interval.
 */
class DateInterval extends NativeDateInterval
{
    /**
     * Fractional part of seconds
     * @var float
     */
    public $fsec = 0.0;

    /**
     * Returns the value of DateInterval object as an ISO 8601 time interval string
     *
     * This string will not necessarily work with DateInterval's constructor
     * as that can handle neither negative numbers nor fractional seconds.
     * Mostly intended for sending to Postgres as a value of interval type.
     *
     * @param  \DateInterval $interval
     * @return string
     */
    public static function formatAsISO8601(NativeDateInterval $interval)
    {
        // prevent returning an empty string
        if (0 == $interval->y && 0 == $interval->m && 0 == $interval->d
            && 0 == $interval->h && 0 == $interval->i && 0 == $interval->s
            && (!($interval instanceof self) || 0 == $interval->fsec)
        ) {
            return 'PT0S';
        }
        $string = 'P';
        $mult   = $interval->invert ? -1 : 1;
        foreach (array('y' => 'Y', 'm' => 'M', 'd' => 'D') as $key => $char) {
            if (0 != $interval->{$key}) {
                $string .= sprintf('%d%s', $interval->{$key} * $mult, $char);
            }
        }
        if (0 != $interval->h || 0 != $interval->i || 0 != $interval->s
            || $interval instanceof self && 0 != $interval->fsec
        ) {
            $string .= 'T';
            foreach (array('h' => 'H', 'i' => 'M') as $key => $char) {
                if (0 != $interval->{$key}) {
                    $string .= sprintf('%d%s', $interval->{$key} * $mult, $char);
                }
            }
            if (0 != $interval->s || $interval instanceof self && 0 != $interval->fsec) {
                if (!$interval instanceof self || 0 == $interval->fsec) {
                    $string .= sprintf('%d%s', $interval->s * $mult, 'S');
                } else {
                    $string .= rtrim(sprintf('%.6f', ($interval->s + $interval->fsec) * $mult), '0');
                    $string .= 'S';
                }
            }
        }

        return $string;
    }

    public function __toString()
    {
        return self::formatAsISO8601($this);
    }
}