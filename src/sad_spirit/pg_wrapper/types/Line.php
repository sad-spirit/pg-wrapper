<?php

/**
 * Wrapper for PHP's pgsql extension providing conversion of complex DB types
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-wrapper/master/LICENSE
 *
 * @package   sad_spirit\pg_wrapper
 * @copyright 2014-2020 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

declare(strict_types=1);

namespace sad_spirit\pg_wrapper\types;

use sad_spirit\pg_wrapper\exceptions\InvalidArgumentException;

/**
 * Class representing 'line' geometric type (PostgreSQL 9.4+)
 *
 * Lines are represented by the linear equation Ax + By + C = 0
 *
 * @property-read float $A
 * @property-read float $B
 * @property-read float $C
 */
final class Line implements ArrayRepresentable
{
    use ReadOnlyProperties;

    /** @var float */
    private $p_A;
    /** @var float */
    private $p_B;
    /** @var float */
    private $p_C;

    public function __construct(float $A, float $B, float $C)
    {
        $this->p_A = $A;
        $this->p_B = $B;
        $this->p_C = $C;
    }

    /**
     * Returns the A coefficient of linear equation
     *
     * @return float
     */
    public function getA(): float
    {
        return $this->p_A;
    }

    /**
     * Returns the B coefficient of linear equation
     *
     * @return float
     */
    public function getB(): float
    {
        return $this->p_B;
    }

    /**
     * Returns the C coefficient of linear equation
     *
     * @return float
     */
    public function getC(): float
    {
        return $this->p_C;
    }

    /**
     * Creates a Line from a given array
     *
     * @param float[] $input
     * @return self
     * @throws InvalidArgumentException
     */
    public static function createFromArray(array $input): self
    {
        if (3 !== count($input)) {
            throw new InvalidArgumentException(
                sprintf("%s() expects an array with exactly three elements", __METHOD__)
            );
        }
        if (array_key_exists('A', $input) && array_key_exists('B', $input) && array_key_exists('C', $input)) {
            return new self($input['A'], $input['B'], $input['C']);
        }
        return new self(array_shift($input), array_shift($input), array_shift($input));
    }
}
