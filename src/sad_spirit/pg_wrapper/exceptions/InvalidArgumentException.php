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

namespace sad_spirit\pg_wrapper\exceptions;

use sad_spirit\pg_wrapper\Exception;

/**
 * Namespaced version of SPL's InvalidArgumentException
 */
class InvalidArgumentException extends \InvalidArgumentException implements Exception
{
    use Stringifier;

    /**
     * Thrown when a method expects a value of several possible types but is given something else
     *
     * In case of single possible type a type hint should be used, obviously
     *
     * @param string $method
     * @param string $expected
     * @param mixed  $given
     * @return InvalidArgumentException
     */
    public static function unexpectedType(string $method, string $expected, $given): self
    {
        return new self(sprintf(
            '%s() expects %s, %s given',
            $method,
            $expected,
            self::stringify($given)
        ));
    }
}
