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
 * @copyright 2014-2024 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

declare(strict_types=1);

namespace sad_spirit\pg_wrapper\types;

use sad_spirit\pg_wrapper\exceptions\InvalidArgumentException;

/**
 * Class representing 'line' geometric type (PostgreSQL 9.4+)
 *
 * Lines are represented by the linear equation Ax + By + C = 0
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
     * @return array Returned array has the same format that is accepted by {@see createFromArray()}
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
     * @return self
     * @throws InvalidArgumentException
     */
    public static function createFromArray(array $input): self
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
