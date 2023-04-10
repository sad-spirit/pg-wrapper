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
 * Class representing a range value from Postgres on PHP side
 *
 * @template Bound
 * @property-read Bound|null $lower
 * @property-read Bound|null $upper
 * @property-read bool       $lowerInclusive
 * @property-read bool       $upperInclusive
 * @property-read bool       $empty
 *
 * @psalm-consistent-constructor
 */
class Range implements ArrayRepresentable, RangeConstructor, \JsonSerializable
{
    use ReadOnlyProperties;

    /** @var Bound|null */
    private $p_lower = null;
    /** @var Bound|null */
    private $p_upper = null;
    /** @var bool */
    private $p_lowerInclusive = true;
    /** @var bool */
    private $p_upperInclusive = false;
    /** @var bool */
    private $p_empty = false;

    public function __construct(
        $lower = null,
        $upper = null,
        bool $lowerInclusive = true,
        bool $upperInclusive = false
    ) {
        $this->p_lower          = $lower;
        $this->p_upper          = $upper;
        $this->p_lowerInclusive = $lowerInclusive && null !== $lower;
        $this->p_upperInclusive = $upperInclusive && null !== $upper;
    }

    /**
     * Returns the range's lower bound
     *
     * @return Bound|null
     */
    public function getLower()
    {
        return $this->p_lower;
    }

    /**
     * Returns the range's upper bound
     *
     * @return Bound|null
     */
    public function getUpper()
    {
        return $this->p_upper;
    }

    /**
     * Returns whether the range's lower bound is inclusive
     *
     * @return bool
     */
    public function isLowerInclusive(): bool
    {
        return $this->p_lowerInclusive;
    }

    /**
     * Returns whether the range's upper bound is inclusive
     *
     * @return bool
     */
    public function isUpperInclusive(): bool
    {
        return $this->p_upperInclusive;
    }

    /**
     * Returns whether the range is empty
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->p_empty;
    }

    /**
     * Sets range as empty
     */
    final protected function setEmpty(): void
    {
        $this->p_empty = true;
    }

    /**
     * Creates an empty Range
     *
     * @return static
     */
    public static function createEmpty(): self
    {
        $range = new static();
        $range->setEmpty();
        return $range;
    }

    /**
     * {@inheritDoc}
     *
     * @return array Returned array has the same format that is accepted by {@see createFromArray()}
     */
    public function jsonSerialize(): array
    {
        if ($this->p_empty) {
            return [
                'empty' => true
            ];
        } else {
            return [
                'lower'          => $this->p_lower,
                'upper'          => $this->p_upper,
                'lowerInclusive' => $this->p_lowerInclusive,
                'upperInclusive' => $this->p_upperInclusive
            ];
        }
    }

    /**
     * Converts the (possibly JSON-encoded) bound passed to {@see createFromArray()}
     *
     * @param mixed $bound
     * @return Bound|null
     */
    protected static function convertBound($bound)
    {
        return $bound;
    }

    /**
     * Creates a Range from a given array
     *
     * @param array $input Expects an array of two bounds or array with the keys named as Range properties
     * @return static
     */
    public static function createFromArray(array $input): self
    {
        if (!empty($input['empty'])) {
            return static::createEmpty();
        }
        foreach (['lower', 'upper', 'lowerInclusive', 'upperInclusive'] as $key) {
            if (array_key_exists($key, $input)) {
                return new static(
                    static::convertBound($input['lower'] ?? null),
                    static::convertBound($input['upper'] ?? null),
                    $input['lowerInclusive'] ?? true,
                    $input['upperInclusive'] ?? false
                );
            }
        }
        return new static(
            static::convertBound(array_shift($input)),
            static::convertBound(array_shift($input))
        );
    }
}
