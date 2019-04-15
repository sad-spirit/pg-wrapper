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

use sad_spirit\pg_wrapper\exceptions\InvalidArgumentException;

/**
 * Base class for geometric types containing an arbitrary number of Points (paths and polygons)
 */
abstract class PointList implements \ArrayAccess, \Countable, \IteratorAggregate
{
    /**
     * Points available through ArrayAccess
     * @var Point[]
     */
    protected $points;

    public function __construct(array $points)
    {
        foreach ($points as $point) {
            $this[] = $point;
        }
    }

    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->points);
    }

    public function offsetGet($offset)
    {
        if (array_key_exists($offset, $this->points)) {
            return $this->points[$offset];
        }

        throw new InvalidArgumentException("Undefined offset '{$offset}'");
    }

    public function offsetSet($offset, $value)
    {
        if (null !== $offset && !ctype_digit((string)$offset)) {
            throw new InvalidArgumentException("Nonnegative numeric offsets expected, '{$offset}' given");
        }
        if (!($value instanceof Point)) {
            throw new InvalidArgumentException(sprintf(
                '%s can contain only instances of Point, %s given',
                __CLASS__, is_object($value) ? 'object(' . get_class($value) . ')' : gettype($value)
            ));
        }

        if (null === $offset) {
            $this->points[] = $value;
        } else {
            $this->points[(int)$offset] = $value;
        }
    }

    public function offsetUnset($offset)
    {
        unset($this->points[$offset]);
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->points);
    }

    public function count()
    {
        return count($this->points);
    }

    public static function createFromArray(array $input)
    {
        $points = [];
        foreach ($input as $point) {
            if (is_array($point)) {
                $point = Point::createFromArray($point);
            }
            $points[] = $point;
        }

        return new static($points);
    }
}