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

/**
 * Class representing a 'path' geometric type
 *
 * Contains a list of points and 'open' property
 *
 * @property-read boolean $open
 */
final class Path extends PointList implements ArrayRepresentable, \JsonSerializable
{
    use ReadOnlyProperties;

    /** @var bool */
    private $openProp;

    public function __construct(bool $open, Point ...$points)
    {
        parent::__construct(...$points);
        $this->openProp = $open;
    }

    /**
     * Returns whether path is open
     *
     * @return bool
     */
    public function isOpen(): bool
    {
        return $this->openProp;
    }

    /**
     * {@inheritDoc}
     *
     * @return array Returned array has the same format that is accepted by {@see createFromArray()}
     */
    public function jsonSerialize(): array
    {
        $points = $this->points;
        array_unshift($points, $this->openProp);
        return $points;
    }

    /**
     * Creates a Path from a given array
     *
     * @param array $input Expects an array of Points or Point-compatible arrays (=two floats), first item
     *                     can also be a bool for $open property of created Path
     * @return self
     */
    public static function createFromArray(array $input): self
    {
        if (is_bool(reset($input))) {
            $open = array_shift($input);
        } else {
            $open = false;
        }

        return new self($open, ...self::createPointArray($input));
    }
}
