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
 * Class representing 'line' geometric type (PostgreSQL 9.4+)
 *
 * @property float A
 * @property float B
 * @property float C
 */
class Line
{
    private $_coeffs = array(
        'A' => 0,
        'B' => 0,
        'C' => 0
    );

    function __construct($A, $B, $C)
    {
        $this->__set('A', $A);
        $this->__set('B', $B);
        $this->__set('C', $C);
    }

    function __get($name)
    {
        if (array_key_exists($name, $this->_coeffs)) {
            return $this->_coeffs[$name];

        } else {
            throw new InvalidArgumentException("Unknown property '{$name}'");
        }
    }

    function __set($name, $value)
    {
        if (array_key_exists($name, $this->_coeffs)) {
            if (!is_numeric($value)) {
                throw new InvalidArgumentException("Line coefficient '{$name}' should be numeric");
            }
            $this->_coeffs[$name] = (double)$value;

        } else {
            throw new InvalidArgumentException("Unknown property '{$name}'");
        }
    }

    function __isset($name)
    {
        return array_key_exists($name, $this->_coeffs);
    }

    /**
     * Creates a Line from a given array
     *
     * @param array $input
     * @return self
     * @throws InvalidArgumentException
     */
    public static function createFromArray(array $input)
    {
        if (3 != count($input)) {
            throw new InvalidArgumentException(sprintf(
                "%s() expects an array with exactly three elements", __METHOD__
            ));
        }
        if (array_key_exists('A', $input) && array_key_exists('B', $input) && array_key_exists('C', $input)) {
            return new self($input['A'], $input['B'], $input['C']);
        }
        return new self(array_shift($input), array_shift($input), array_shift($input));
    }
}