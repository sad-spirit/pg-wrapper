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

use sad_spirit\pg_wrapper\exceptions\InvalidArgumentException;

/**
 * Represents a point in two-dimensional space
 */
final readonly class Point implements ArrayRepresentable, \JsonSerializable
{
    public function __construct(
        public float $x,
        public float $y
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @return array Returned array has the same format that is accepted by
     *         {@see \sad_spirit\pg_wrapper\types\Point::createFromArray() createFromArray()}
     */
    public function jsonSerialize(): array
    {
        return [
            'x' => $this->x,
            'y' => $this->y
        ];
    }

    /**
     * Creates a Point from a given array
     *
     * @param array $input Expects an array of two floats
     * @throws InvalidArgumentException
     */
    public static function createFromArray(array $input): static
    {
        if (2 !== \count($input)) {
            throw new InvalidArgumentException(
                \sprintf("%s() expects an array with exactly two elements", __METHOD__)
            );
        }
        if (\array_key_exists('x', $input) && \array_key_exists('y', $input)) {
            return new self($input['x'], $input['y']);
        }
        return new self(\array_shift($input), \array_shift($input));
    }
}
