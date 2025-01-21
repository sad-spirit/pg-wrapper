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
 * Base class for geometric types containing exactly two Points (boxes and line segments)
 */
abstract readonly class PointPair implements ArrayRepresentable, \JsonSerializable
{
    final public function __construct(
        public Point $start,
        public Point $end
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @return array Returned array has the same format that is accepted by {@see createFromArray()}
     */
    public function jsonSerialize(): array
    {
        return [
            'start' => $this->start,
            'end'   => $this->end
        ];
    }

    /**
     * Creates an instance of PointPair from a given array
     *
     * @param array $input Expects an array with two Point-compatible values
     * @throws InvalidArgumentException
     */
    public static function createFromArray(array $input): static
    {
        if (2 !== \count($input)) {
            throw new InvalidArgumentException(
                \sprintf("%s() expects an array with exactly two elements", __METHOD__)
            );
        }
        if (\array_key_exists('start', $input) && \array_key_exists('end', $input)) {
            $start = $input['start'];
            $end   = $input['end'];
        } else {
            $start = \array_shift($input);
            $end   = \array_shift($input);
        }
        if (\is_array($start)) {
            $start = Point::createFromArray($start);
        }
        if (\is_array($end)) {
            $end = Point::createFromArray($end);
        }
        return new static($start, $end);
    }
}
