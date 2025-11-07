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
 * Represents a value of tid (tuple identifier) type
 *
 * tid describes the physical location of a tuple (row) within a table and
 * consists of a block number and tuple index within that block. Both of these
 * are non-negative integers.
 */
final readonly class Tid implements ArrayRepresentable, \JsonSerializable
{
    public int|string $block;
    public int $tuple;

    /**
     * Constructor, checks that arguments are non-negative
     *
     * @param int|numeric-string $block
     * @param int $tuple
     */
    public function __construct(int|string $block, int $tuple)
    {
        if (
            \is_string($block) && !\ctype_digit($block)
            || 0 > $block
        ) {
            throw new InvalidArgumentException("Tid \$block field should be a non-negative integer");
        }
        if (0 > $tuple) {
            throw new InvalidArgumentException("Tid \$tuple field should be a non-negative integer");
        }

        $this->block = $block;
        $this->tuple = $tuple;
    }

    /**
     * {@inheritDoc}
     *
     * @return array Returned array has the same format that is accepted by
     *         {@see \sad_spirit\pg_wrapper\types\Tid::createFromArray() createFromArray()}
     */
    public function jsonSerialize(): array
    {
        return [
            'block' => $this->block,
            'tuple' => $this->tuple
        ];
    }

    /**
     * Creates a Tid from a given array
     *
     * @param array $input Expects an array of two integers
     * @throws InvalidArgumentException
     */
    public static function createFromArray(array $input): static
    {
        if (2 !== \count($input)) {
            throw new InvalidArgumentException(
                \sprintf("%s() expects an array with exactly two elements", __METHOD__)
            );
        }
        if (\array_key_exists('block', $input) && \array_key_exists('tuple', $input)) {
            return new self($input['block'], $input['tuple']);
        }
        return new self(\array_shift($input), \array_shift($input));
    }
}
