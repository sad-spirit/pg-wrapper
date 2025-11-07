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
 * Class representing a 'path' geometric type
 *
 * Contains a list of points and 'open' property
 */
final readonly class Path extends PointList implements ArrayRepresentable, \JsonSerializable
{
    public function __construct(
        public bool $open,
        Point ...$points
    ) {
        parent::__construct(...$points);
    }

    /**
     * {@inheritDoc}
     *
     * @return array Returned array has the same format that is accepted by
     *         {@see \sad_spirit\pg_wrapper\types\Path::createFromArray() createFromArray()}
     */
    public function jsonSerialize(): array
    {
        $points = $this->points;
        \array_unshift($points, $this->open);
        return $points;
    }

    /**
     * Creates a Path from a given array
     *
     * @param array $input Expects an array of `Point`s or `Point`-compatible arrays (=two floats), first item
     *                     can also be a `bool` for `$open` property of created `Path`
     */
    public static function createFromArray(array $input): static
    {
        $open = \is_bool(\reset($input)) && \array_shift($input);

        return new self($open, ...self::createPointArray($input));
    }
}
