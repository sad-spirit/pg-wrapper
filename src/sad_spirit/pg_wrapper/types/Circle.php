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
 * Represents a circle, defined by a center point and radius
 *
 * @property-read Point $center
 * @property-read float $radius
 */
final class Circle implements ArrayRepresentable
{
    use ReadOnlyProperties;

    /** @var Point */
    private $p_center;
    /** @var float */
    private $p_radius;

    public function __construct(Point $center, float $radius)
    {
        $this->p_center = $center;
        $this->p_radius = $radius;
    }

    /**
     * Returns the circle's central Point
     *
     * @return Point
     */
    public function getCenter(): Point
    {
        return $this->p_center;
    }

    /**
     * Returns the circle's radius
     *
     * @return float
     */
    public function getRadius(): float
    {
        return $this->p_radius;
    }

    /**
     * Creates a Circle from a given array
     *
     * @param array $input Expects an array with two arguments, one Point-compatible and one float
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
        if (array_key_exists('center', $input) && array_key_exists('radius', $input)) {
            $center = $input['center'];
            $radius = $input['radius'];
        } else {
            $center = array_shift($input);
            $radius = array_shift($input);
        }
        if (is_array($center)) {
            $center = Point::createFromArray($center);
        }
        return new self($center, $radius);
    }
}
