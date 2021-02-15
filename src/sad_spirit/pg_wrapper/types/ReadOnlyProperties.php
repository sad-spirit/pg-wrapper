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
 * @copyright 2014-2021 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

declare(strict_types=1);

namespace sad_spirit\pg_wrapper\types;

use sad_spirit\pg_wrapper\exceptions\{
    BadMethodCallException,
    InvalidArgumentException
};

/**
 * Exposes the object's getters also as read-only magic properties
 */
trait ReadOnlyProperties
{
    /**
     * If the class has getName() or isName() method implemented, it is called for the value of 'name' property
     *
     * @param string $name
     * @return mixed
     * @throws InvalidArgumentException In case of non-existent methods
     */
    public function __get(string $name)
    {
        if (method_exists($this, 'get' . $name)) {
            return $this->{'get' . $name}();
        } elseif (method_exists($this, 'is' . $name)) {
            return $this->{'is' . $name}();
        } else {
            throw new InvalidArgumentException("Unknown property '{$name}'");
        }
    }

    /**
     * Checks whether the class has getName() or isName() method implemented
     *
     * @param string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return method_exists($this, 'get' . $name)
               || method_exists($this, 'is' . $name);
    }

    /**
     * Prevents changing the object's properties
     *
     * @param string $name
     * @param mixed $value
     * @throws BadMethodCallException
     */
    public function __set(string $name, $value): void
    {
        throw new BadMethodCallException(__CLASS__ . " objects are immutable");
    }

    /**
     * Prevents unsetting the object's properties
     *
     * @param string $name
     * @throws BadMethodCallException
     */
    public function __unset(string $name): void
    {
        throw new BadMethodCallException(__CLASS__ . " objects are immutable");
    }
}
