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

/**
 * Class representing 'polygon' geometric type
 */
final readonly class Polygon extends PointList implements ArrayRepresentable, \JsonSerializable
{
    /**
     * {@inheritDoc}
     *
     * @return array Returned array has the same format that is accepted by {@see createFromArray()}
     */
    public function jsonSerialize(): array
    {
        return $this->points;
    }

    /**
     * Creates a Polygon from a given array
     *
     * @param array $input Expects an array of Points or Point-compatible arrays (=two floats)
     */
    public static function createFromArray(array $input): static
    {
        return new self(...self::createPointArray($input));
    }
}
