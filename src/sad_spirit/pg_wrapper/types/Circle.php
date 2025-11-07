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
 * Represents a circle, defined by a center point and radius
 */
final readonly class Circle implements ArrayRepresentable, \JsonSerializable
{
    public function __construct(
        public Point $center,
        public float $radius
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @return array Returned array has the same format that is accepted by
     *         {@see \sad_spirit\pg_wrapper\types\Circle::createFromArray() createFromArray()}
     */
    public function jsonSerialize(): array
    {
        return [
            'center' => $this->center,
            'radius' => $this->radius
        ];
    }

    /**
     * Creates a Circle from a given array
     *
     * @param array $input Expects an array with two arguments, one Point-compatible and one float
     * @throws InvalidArgumentException
     */
    public static function createFromArray(array $input): static
    {
        if (2 !== \count($input)) {
            throw new InvalidArgumentException(
                \sprintf("%s() expects an array with exactly two elements", __METHOD__)
            );
        }
        if (\array_key_exists('center', $input) && \array_key_exists('radius', $input)) {
            $center = $input['center'];
            $radius = $input['radius'];
        } else {
            $center = \array_shift($input);
            $radius = \array_shift($input);
        }
        if (\is_array($center)) {
            $center = Point::createFromArray($center);
        }
        return new self($center, $radius);
    }
}
