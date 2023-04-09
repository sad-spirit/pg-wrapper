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
 * Base class for geometric types containing exactly two Points (boxes and line segments)
 *
 * @property-read Point $start
 * @property-read Point $end
 */
abstract class PointPair implements ArrayRepresentable, \JsonSerializable
{
    use ReadOnlyProperties;

    /** @var Point */
    private $p_start;
    /** @var Point */
    private $p_end;

    final public function __construct(Point $start, Point $end)
    {
        $this->p_start = $start;
        $this->p_end   = $end;
    }

    /**
     * Returns the first Point of line segment (or first corner of Box)
     *
     * @return Point
     */
    public function getStart(): Point
    {
        return $this->p_start;
    }

    /**
     * Returns the last Point of line segment (or second corner of Box)
     *
     * @return Point
     */
    public function getEnd(): Point
    {
        return $this->p_end;
    }

    /**
     * {@inheritDoc}
     *
     * @return array Returned array has the same format that is accepted by {@see createFromArray()}
     */
    public function jsonSerialize(): array
    {
        return [
            'start' => $this->p_start,
            'end'   => $this->p_end
        ];
    }

    /**
     * Creates an instance of PointPair from a given array
     *
     * @param array $input Expects an array with two Point-compatible values
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
        if (array_key_exists('start', $input) && array_key_exists('end', $input)) {
            $start = $input['start'];
            $end   = $input['end'];
        } else {
            $start = array_shift($input);
            $end   = array_shift($input);
        }
        if (is_array($start)) {
            $start = Point::createFromArray($start);
        }
        if (is_array($end)) {
            $end = Point::createFromArray($end);
        }
        return new static($start, $end);
    }
}
