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

declare(strict_types=1);

namespace sad_spirit\pg_wrapper\types;

use sad_spirit\pg_wrapper\exceptions\InvalidArgumentException;

/**
 * Represents a value of tid (tuple identifier) type
 *
 * tid describes the physical location of a tuple (row) within a table and
 * consists of a block number and tuple index within that block. Both of these
 * are nonnegative integers.
 *
 * @property integer $block
 * @property integer $tuple
 */
class Tid
{
    private $_props = [
        'block' => 0,
        'tuple' => 0
    ];

    public function __construct($block, $tuple)
    {
        $this->__set('block', $block);
        $this->__set('tuple', $tuple);
    }

    public function __get($name)
    {
        if (array_key_exists($name, $this->_props)) {
            return $this->_props[$name];

        } else {
            throw new InvalidArgumentException("Unknown property '{$name}'");
        }
    }

    public function __set($name, $value)
    {
        switch ($name) {
        case 'block':
        case 'tuple':
            if (ctype_digit((string)$value)) {
                $this->_props[$name] = $value;
            } else {
                throw new InvalidArgumentException("Tid {$name} field should be a nonnegative integer");
            }
            break;
        default:
            throw new InvalidArgumentException("Unknown property '{$name}'");
        }
    }

    public function __isset($name)
    {
        return array_key_exists($name, $this->_props);
    }

    /**
     * Creates a Tid from a given array
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
        if (array_key_exists('block', $input) && array_key_exists('tuple', $input)) {
            return new self($input['block'], $input['tuple']);
        }
        return new self(array_shift($input), array_shift($input));
    }
}