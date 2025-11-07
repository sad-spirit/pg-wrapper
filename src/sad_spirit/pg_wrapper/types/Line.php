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
 * Class representing 'line' geometric type (PostgreSQL 9.4+)
 *
 * Lines are represented by the linear equation `Ax + By + C = 0`
 */
final readonly class Line implements ArrayRepresentable, \JsonSerializable
{
    public function __construct(
        public float $A,
        public float $B,
        public float $C
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @return array Returned array has the same format that is accepted by
     *         {@see \sad_spirit\pg_wrapper\types\Line::createFromArray() createFromArray()}
     */
    public function jsonSerialize(): array
    {
        return [
            'A' => $this->A,
            'B' => $this->B,
            'C' => $this->C
        ];
    }

    /**
     * Creates a Line from a given array
     *
     * @param array $input Expects an array of three floats
     * @throws InvalidArgumentException
     */
    public static function createFromArray(array $input): static
    {
        if (3 !== \count($input)) {
            throw new InvalidArgumentException(
                \sprintf("%s() expects an array with exactly three elements", __METHOD__)
            );
        }
        if (\array_key_exists('A', $input) && \array_key_exists('B', $input) && \array_key_exists('C', $input)) {
            return new self($input['A'], $input['B'], $input['C']);
        }
        return new self(\array_shift($input), \array_shift($input), \array_shift($input));
    }
}
