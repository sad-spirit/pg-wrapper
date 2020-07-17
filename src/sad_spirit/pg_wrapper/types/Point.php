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
 * Represents a point in two-dimensional space
 *
 * @property float $x
 * @property float $y
 */
class Point
{
    private $coordinates = [
        'x' => 0,
        'y' => 0
    ];

    public function __construct($x, $y)
    {
        $this->__set('x', $x);
        $this->__set('y', $y);
    }

    public function __get($name)
    {
        if ('x' === $name || 'y' === $name) {
            return $this->coordinates[$name];

        } else {
            throw new InvalidArgumentException("Unknown property '{$name}'");
        }
    }

    public function __set($name, $value)
    {
        if ('x' !== $name && 'y' !== $name) {
            throw new InvalidArgumentException("Unknown property '{$name}'");

        } elseif (!is_numeric($value)) {
            throw new InvalidArgumentException("Point '{$name}' coordinate should be numeric");
        }

        $this->coordinates[$name] = (double)$value;
    }

    public function __isset($name)
    {
        return 'x' === $name || 'y' === $name;
    }

    /**
     * Creates a Point from a given array
     *
     * @param array $input
     * @return self
     * @throws InvalidArgumentException
     */
    public static function createFromArray(array $input)
    {
        if (2 != count($input)) {
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
