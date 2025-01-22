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
 * Class representing a multirange value from Postgres 14+ on PHP side
 *
 * @template T of Range
 * @implements \ArrayAccess<int, T>
 * @implements \IteratorAggregate<int, T>
 */
readonly class MultiRange implements ArrayRepresentable, \ArrayAccess, \Countable, \IteratorAggregate, \JsonSerializable
{
    /**
     * @var list<T>
     */
    private array $items;

    /**
     * Only instances of this class will be allowed as items
     *
     * @return class-string<T>
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
            /** @psalm-suppress NoValue */
            if (!$item instanceof $rangeClass) {
                throw new InvalidArgumentException(\sprintf(
                    '%s can contain only instances of %s, instance of %s given',
                    self::class,
                    $rangeClass,
                    $item::class
                ));
            }
        }
        $this->items = \array_values($items);
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
    final public function offsetSet($offset, $value): never
    {
        throw new BadMethodCallException(self::class . " objects are immutable");
    }

    /**
     * Prohibits changing the items
     * {@inheritDoc}
     * @throws BadMethodCallException
     */
    final public function offsetUnset($offset): never
    {
        throw new BadMethodCallException(self::class . " objects are immutable");
    }

    /**
     * {@inheritDoc}
     * @psalm-return \ArrayIterator<int<0, max>, T>
     * @phpstan-return \ArrayIterator<int, T>
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
    final public static function createFromArray(array $input): static
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
                    \is_object($range) ? 'object(' . $range::class . ')' : \gettype($range)
                ));
            }
            $ranges[] = $range;
        }
        return new static(...$ranges);
    }

    /**
     * {@inheritDoc}
     *
     * @return list<T>
     */
    final public function jsonSerialize(): array
    {
        return $this->items;
    }
}
