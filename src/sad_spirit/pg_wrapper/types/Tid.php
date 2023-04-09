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

use sad_spirit\pg_wrapper\exceptions\InvalidArgumentException;

/**
 * Represents a value of tid (tuple identifier) type
 *
 * tid describes the physical location of a tuple (row) within a table and
 * consists of a block number and tuple index within that block. Both of these
 * are non-negative integers.
 *
 * @property-read integer $block
 * @property-read integer $tuple
 */
final class Tid implements ArrayRepresentable, \JsonSerializable
{
    use ReadOnlyProperties;

    /** @var int */
    private $p_block;
    /** @var int */
    private $p_tuple;

    public function __construct(int $block, int $tuple)
    {
        foreach (['block', 'tuple'] as $name) {
            if (0 > $$name) {
                throw new InvalidArgumentException("Tid {$name} field should be a non-negative integer");
            }
        }

        $this->p_block = $block;
        $this->p_tuple = $tuple;
    }

    /**
     * Returns the block's number within a table
     *
     * @return int
     */
    public function getBlock(): int
    {
        return $this->p_block;
    }

    /**
     * Returns the tuple's index within a block
     *
     * @return int
     */
    public function getTuple(): int
    {
        return $this->p_tuple;
    }

    /**
     * {@inheritDoc}
     *
     * @return array Returned array has the same format that is accepted by {@see createFromArray()}
     */
    public function jsonSerialize(): array
    {
        return [
            'block' => $this->p_block,
            'tuple' => $this->p_tuple
        ];
    }

    /**
     * Creates a Tid from a given array
     *
     * @param array $input Expects an array of two integers
     * @return self
     * @throws InvalidArgumentException
     */
    public static function createFromArray(array $input): self
    {
        if (2 !== count($input)) {
            throw new InvalidArgumentException(
                sprintf("%s() expects an array with exactly two elements", __METHOD__)
            );
        }
        if (array_key_exists('block', $input) && array_key_exists('tuple', $input)) {
            return new self($input['block'], $input['tuple']);
        }
        return new self(array_shift($input), array_shift($input));
    }
}
