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
 * Class representing a range value from Postgres on PHP side
 *
 * @template Bound
 *
 * @psalm-consistent-constructor
 */
readonly class Range implements ArrayRepresentable, RangeConstructor, \JsonSerializable
{
    /** @var Bound|null */
    public mixed $lower;
    /** @var Bound|null */
    public mixed $upper;
    public bool $lowerInclusive;
    public bool $upperInclusive;
    public bool $empty;

    public function __construct(
        mixed $lower = null,
        mixed $upper = null,
        bool $lowerInclusive = true,
        bool $upperInclusive = false,
        bool $empty = false
    ) {
        if (!$empty) {
            $this->lower          = $lower;
            $this->upper          = $upper;
            $this->lowerInclusive = $lowerInclusive && null !== $lower;
            $this->upperInclusive = $upperInclusive && null !== $upper;
        } else {
            $this->lower          = null;
            $this->upper          = null;
            $this->lowerInclusive = false;
            $this->upperInclusive = false;
        }
        $this->empty = $empty;
    }

    /**
     * Creates an empty Range
     *
     * @return static
     */
    public static function createEmpty(): static
    {
        return new static(empty: true);
    }

    /**
     * {@inheritDoc}
     *
     * @return array Returned array has the same format that is accepted by {@see createFromArray()}
     */
    public function jsonSerialize(): array
    {
        if ($this->empty) {
            return [
                'empty' => true
            ];
        } else {
            return [
                'lower'          => $this->lower,
                'upper'          => $this->upper,
                'lowerInclusive' => $this->lowerInclusive,
                'upperInclusive' => $this->upperInclusive
            ];
        }
    }

    /**
     * Converts the (possibly JSON-encoded) bound passed to {@see createFromArray()}
     *
     * @return Bound|null
     */
    protected static function convertBound(mixed $bound): mixed
    {
        return $bound;
    }

    /**
     * Creates a Range from a given array
     *
     * @param array $input Expects an array of two bounds or array with the keys named as Range properties
     * @return static
     */
    public static function createFromArray(array $input): static
    {
        if (!empty($input['empty'])) {
            return static::createEmpty();
        }
        foreach (['lower', 'upper', 'lowerInclusive', 'upperInclusive'] as $key) {
            if (\array_key_exists($key, $input)) {
                return new static(
                    static::convertBound($input['lower'] ?? null),
                    static::convertBound($input['upper'] ?? null),
                    $input['lowerInclusive'] ?? true,
                    $input['upperInclusive'] ?? false
                );
            }
        }
        return new static(
            static::convertBound(\array_shift($input)),
            static::convertBound(\array_shift($input))
        );
    }
}
