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
 * Represents a point in two-dimensional space
 *
 * @property-read float $x
 * @property-read float $y
 */
final class Point implements ArrayRepresentable, \JsonSerializable
{
    use ReadOnlyProperties;

    /** @var float */
    private $p_x;
    /** @var float */
    private $p_y;

    public function __construct(float $x, float $y)
    {
        $this->p_x = $x;
        $this->p_y = $y;
    }

    /**
     * Returns the point's X coordinate
     *
     * @return float
     */
    public function getX(): float
    {
        return $this->p_x;
    }

    /**
     * Returns the point's Y coordinate
     *
     * @return float
     */
    public function getY(): float
    {
        return $this->p_y;
    }

    /**
     * {@inheritDoc}
     *
     * @return array Returned array has the same format that is accepted by {@see createFromArray()}
     */
    public function jsonSerialize(): array
    {
        return [
            'x' => $this->p_x,
            'y' => $this->p_y
        ];
    }

    /**
     * Creates a Point from a given array
     *
     * @param array $input Expects an array of two floats
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
        if (array_key_exists('x', $input) && array_key_exists('y', $input)) {
            return new self($input['x'], $input['y']);
        }
        return new self(array_shift($input), array_shift($input));
    }
}
