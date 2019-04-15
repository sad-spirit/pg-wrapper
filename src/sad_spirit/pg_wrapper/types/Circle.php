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
 * @copyright 2014-2017 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

namespace sad_spirit\pg_wrapper\types;

use sad_spirit\pg_wrapper\exceptions\InvalidArgumentException;

/**
 * Represents a cirle, defined by a center point and radius
 *
 * @property Point $center
 * @property float $radius
 */
class Circle
{
    private $_props = [
        'center' => null,
        'radius' => 0
    ];

    function __construct(Point $center, $radius)
    {
        $this->__set('center', $center);
        $this->__set('radius', $radius);
    }

    function __get($name)
    {
        if ('center' === $name || 'radius' === $name) {
            return $this->_props[$name];

        } else {
            throw new InvalidArgumentException("Unknown property '{$name}'");
        }
    }

    function __set($name, $value)
    {
        if ('center' === $name) {
            if (!($value instanceof Point)) {
                throw new InvalidArgumentException("Circle center should be a Point");
            }
            $this->_props[$name] = $value;

        } elseif ('radius' === $name) {
            if (!is_numeric($value)) {
                throw new InvalidArgumentException("Circle radius should be numeric");
            }
            $this->_props[$name] = (double)$value;

        } else {
            throw new InvalidArgumentException("Unknown property '{$name}'");
        }
    }

    function __isset($name)
    {
        return 'center' === $name || 'radius' === $name;
    }

    /**
     * Creates a Circle from a given array
     *
     * @param array $input
     * @return self
     * @throws InvalidArgumentException
     */
    public static function createFromArray(array $input)
    {
        if (2 != count($input)) {
            throw new InvalidArgumentException(sprintf(
                "%s() expects an array with exactly two elements", __METHOD__
            ));
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