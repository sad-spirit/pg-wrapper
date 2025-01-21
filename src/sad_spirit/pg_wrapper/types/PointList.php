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
abstract readonly class PointList implements \ArrayAccess, \Countable, \IteratorAggregate
{
    /**
     * Points available through ArrayAccess
     * @var list<Point>
     */
    protected array $points;

    public function __construct(Point ...$points)
    {
        $this->points = \array_values($points);
    }

    /**
     * {@inheritDoc}
     */
    public function offsetExists($offset): bool
    {
        return \array_key_exists($offset, $this->points);
    }

    /**
     * {@inheritDoc}
     * @return Point
     */
    public function offsetGet($offset): Point
    {
        if (\array_key_exists($offset, $this->points)) {
            return $this->points[$offset];
        }

        throw new InvalidArgumentException("Undefined offset '{$offset}'");
    }

    /**
     * Prohibits changing the array
     * {@inheritDoc}
     * @throws BadMethodCallException
     */
    public function offsetSet($offset, $value): never
    {
        throw new BadMethodCallException(self::class . " objects are immutable");
    }

    /**
     * Prohibits changing the array
     * {@inheritDoc}
     * @throws BadMethodCallException
     */
    public function offsetUnset($offset): never
    {
        throw new BadMethodCallException(self::class . " objects are immutable");
    }

    /**
     * {@inheritDoc}
     * @psalm-return \ArrayIterator<int<0, max>, Point>
     * @phpstan-return \ArrayIterator<int, Point>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->points);
    }

    /**
     * {@inheritDoc}
     */
    public function count(): int
    {
        return \count($this->points);
    }

    /**
     * Converts an array containing Points
     *
     * @param array $input Expects an array of Points or Point-compatible arrays (=two floats)
     * @return list<Point>
     */
    protected static function createPointArray(array $input): array
    {
        $points = [];
        foreach ($input as $point) {
            if (\is_array($point)) {
                $point = Point::createFromArray($point);
            } elseif (!$point instanceof Point) {
                throw new InvalidArgumentException(\sprintf(
                    "%s() expects an array containing Points or arrays convertible to Point, %s found",
                    __METHOD__,
                    \is_object($point) ? 'object(' . $point::class . ')' : \gettype($point)
                ));
            }
            $points[] = $point;
        }
        return $points;
    }
}
