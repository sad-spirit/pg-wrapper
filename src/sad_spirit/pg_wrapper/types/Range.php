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
 * Class representing a range value from Postgres on PHP side
 *
 * @property mixed $lower
 * @property mixed $upper
 * @property bool  $lowerInclusive
 * @property bool  $upperInclusive
 * @property bool  $empty
 */
class Range
{
    private $props = [
        'lower'          => null,
        'upper'          => null,
        'lowerInclusive' => true,
        'upperInclusive' => false,
        'empty'          => false
    ];

    public function __construct($lower = null, $upper = null, bool $lowerInclusive = true, bool $upperInclusive = false)
    {
        $this->__set('lower', $lower);
        $this->__set('upper', $upper);
        $this->__set('lowerInclusive', $lowerInclusive);
        $this->__set('upperInclusive', $upperInclusive);
    }

    /**
     * Creates an empty Range
     *
     * @return self
     */
    public static function createEmpty()
    {
        $range = new static();
        $range->empty = true;

        return $range;
    }

    /**
     * Creates a Range from a given array
     *
     * @param array $input
     * @return self
     */
    public static function createFromArray(array $input)
    {
        if (!empty($input['empty'])) {
            return static::createEmpty();
        }
        foreach (['lower', 'upper', 'lowerInclusive', 'upperInclusive'] as $key) {
            if (array_key_exists($key, $input)) {
                return new static(
                    array_key_exists('lower', $input) ? $input['lower'] : null,
                    array_key_exists('upper', $input) ? $input['upper'] : null,
                    array_key_exists('lowerInclusive', $input) ? $input['lowerInclusive'] : true,
                    array_key_exists('upperInclusive', $input) ? $input['upperInclusive'] : false
                );
            }
        }
        return new static(array_shift($input), array_shift($input));
    }

    public function __get($name)
    {
        if (array_key_exists($name, $this->props)) {
            return $this->props[$name];

        } else {
            throw new InvalidArgumentException("Unknown property '{$name}'");
        }
    }

    public function __set($name, $value)
    {
        switch ($name) {
            case 'upper':
            case 'lower':
                $this->props[$name] = $value;
                break;

            case 'upperInclusive':
            case 'lowerInclusive':
            case 'empty':
                $this->props[$name] = (bool)$value;
                break;

            default:
                throw new InvalidArgumentException("Unknown property '{$name}'");
        }
    }

    public function __isset($name)
    {
        return array_key_exists($name, $this->props);
    }
}
