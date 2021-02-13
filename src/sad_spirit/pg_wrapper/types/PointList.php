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

use sad_spirit\pg_wrapper\exceptions\{
    BadMethodCallException,
    InvalidArgumentException
};

/**
 * Base class for geometric types containing an arbitrary number of Points (paths and polygons)
 *
 * @implements \ArrayAccess<int, Point>
 * @implements \IteratorAggregate<int, Point>
 */
abstract class PointList implements \ArrayAccess, \Countable, \IteratorAggregate
{
    /**
     * Points available through ArrayAccess
     * @var Point[]
     */
    protected $points = [];

    public function __construct(Point ...$points)
    {
        $this->points = $points;
    }

    /**
     * {@inheritDoc}
     */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->points);
    }

    /**
     * {@inheritDoc}
     * @return Point
     */
    public function offsetGet($offset)
    {
        if (array_key_exists($offset, $this->points)) {
            return $this->points[$offset];
        }

        throw new InvalidArgumentException("Undefined offset '{$offset}'");
    }

    /**
     * Prohibits changing the array
     * {@inheritDoc}
     * @throws BadMethodCallException
     */
    public function offsetSet($offset, $value)
    {
        throw new BadMethodCallException(__CLASS__ . " objects are immutable");
    }

    /**
     * Prohibits changing the array
     * {@inheritDoc}
     * @throws BadMethodCallException
     */
    public function offsetUnset($offset)
    {
        throw new BadMethodCallException(__CLASS__ . " objects are immutable");
    }

    /**
     * {@inheritDoc}
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->points);
    }

    /**
     * {@inheritDoc}
     */
    public function count()
    {
        return count($this->points);
    }

    /**
     * Converts an array containing Points
     *
     * @param array $input Expects an array of Points or Point-compatible arrays (=two floats)
     * @return Point[]
     */
    protected static function createPointArray(array $input): array
    {
        $points = [];
        foreach ($input as $point) {
            if (is_array($point)) {
                $point = Point::createFromArray($point);
            } elseif (!$point instanceof Point) {
                throw new InvalidArgumentException(sprintf(
                    "%s() expects an array containing Points or arrays convertible to Point, %s found",
                    __METHOD__,
                    is_object($point) ? 'object(' . get_class($point) . ')' : gettype($point)
                ));
            }
            $points[] = $point;
        }
        return $points;
    }
}
