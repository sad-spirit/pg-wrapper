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
 * @copyright 2014-2021 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

declare(strict_types=1);

namespace sad_spirit\pg_wrapper\types;

use sad_spirit\pg_wrapper\exceptions\{
    BadMethodCallException,
    InvalidArgumentException
};

/**
 * Class representing a multirange value from Postgres 14+ on PHP side
 *
 * @template T of Range
 * @implements \ArrayAccess<int, T>
 * @implements \IteratorAggregate<int, T>
 */
class MultiRange implements ArrayRepresentable, \ArrayAccess, \Countable, \IteratorAggregate
{
    /**
     * @var array<int, T>
     */
    private $items;

    /**
     * Only instances of this class will be allowed as items
     *
     * @return class-string<Range>
     */
    public static function getItemClass(): string
    {
        return Range::class;
    }

    /**
     * Constructor, checks that incoming Ranges are of the needed type
     *
     * @param T ...$items
     */
    final public function __construct(Range ...$items)
    {
        $rangeClass = static::getItemClass();
        foreach ($items as $item) {
            if (!$item instanceof $rangeClass) {
                throw new InvalidArgumentException(sprintf(
                    '%s can contain only instances of %s, instance of %s given',
                    __CLASS__,
                    $rangeClass,
                    \get_class($item)
                ));
            }
        }
        $this->items = $items;
    }

    /**
     * {@inheritDoc}
     */
    final public function offsetExists($offset): bool
    {
        return \array_key_exists($offset, $this->items);
    }

    /**
     * {@inheritDoc}
     * @return T
     * @throws InvalidArgumentException
     */
    final public function offsetGet($offset): Range
    {
        if (\array_key_exists($offset, $this->items)) {
            return $this->items[$offset];
        }

        throw new InvalidArgumentException("Undefined offset '$offset'");
    }

    /**
     * Prohibits changing the items
     * {@inheritDoc}
     * @throws BadMethodCallException
     */
    final public function offsetSet($offset, $value): void
    {
        throw new BadMethodCallException(__CLASS__ . " objects are immutable");
    }

    /**
     * Prohibits changing the items
     * {@inheritDoc}
     * @throws BadMethodCallException
     */
    final public function offsetUnset($offset): void
    {
        throw new BadMethodCallException(__CLASS__ . " objects are immutable");
    }

    /**
     * {@inheritDoc}
     * @return \ArrayIterator<int, T>
     */
    final public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    /**
     * {@inheritDoc}
     */
    final public function count(): int
    {
        return \count($this->items);
    }

    /**
     * Creates a MultiRange from a given array
     *
     * @param array $input
     * @return static
     * @throws InvalidArgumentException
     */
    final public static function createFromArray(array $input): self
    {
        $rangeClass = static::getItemClass();
        $ranges     = [];
        foreach ($input as $range) {
            if (\is_array($range)) {
                $range = \call_user_func([$rangeClass, 'createFromArray'], $range);
            } elseif (!$range instanceof $rangeClass) {
                throw new InvalidArgumentException(\sprintf(
                    "%s() expects an array containing compatible Ranges or arrays convertible to Range, %s found",
                    __METHOD__,
                    is_object($range) ? 'object(' . \get_class($range) . ')' : \gettype($range)
                ));
            }
            $ranges[] = $range;
        }
        return new static(...$ranges);
    }
}
