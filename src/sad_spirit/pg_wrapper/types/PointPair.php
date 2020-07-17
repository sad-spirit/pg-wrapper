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
 * Base class for geometric types containing exactly two Points (boxes and line segments)
 *
 * @property Point $start
 * @property Point $end
 */
abstract class PointPair
{
    private $points = [
        'start' => null,
        'end'   => null
    ];

    public function __construct(Point $start, Point $end)
    {
        $this->points['start'] = $start;
        $this->points['end']   = $end;
    }

    public function __get($name)
    {
        if ('start' === $name || 'end' === $name) {
            return $this->points[$name];

        } else {
            throw new InvalidArgumentException("Unknown property '{$name}'");
        }
    }

    public function __set($name, $value)
    {
        if ('start' === $name || 'end' === $name) {
            if (!($value instanceof Point)) {
                throw new InvalidArgumentException(
                    sprintf("%s '%s' property should be a Point", __CLASS__, $name)
                );
            }
            $this->points[$name] = $value;

        } else {
            throw new InvalidArgumentException("Unknown property '{$name}'");
        }
    }

    public function __isset($name)
    {
        return 'start' === $name || 'end' === $name;
    }

    /**
     * Creates an instance of PointPair from a given array
     *
     * @param array $input
     * @return self
     * @throws InvalidArgumentException
     */
    public static function createFromArray(array $input): self
    {
        if (2 != count($input)) {
            throw new InvalidArgumentException(
                sprintf("%s() expects an array with exactly two elements", __METHOD__)
            );
        }
        if (array_key_exists('start', $input) && array_key_exists('end', $input)) {
            $start = $input['start'];
            $end   = $input['end'];
        } else {
            $start = array_shift($input);
            $end   = array_shift($input);
        }
        if (is_array($start)) {
            $start = Point::createFromArray($start);
        }
        if (is_array($end)) {
            $end = Point::createFromArray($end);
        }
        return new static($start, $end);
    }
}
