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

namespace sad_spirit\pg_wrapper;

/**
 * Interface for type converters to and from native DB format
 */
interface TypeConverter
{
    /**
     * Converts PHP variable to a native format
     *
     * @param mixed $value
     * @return string|null
     */
    public function output($value): ?string;

    /**
     * Parses a native value into PHP variable
     *
     * Throws exception if parsing process is finished
     * before the string is ended.
     *
     * @param string|null $native
     * @return mixed
     */
    public function input(?string $native);


    /**
     * Number of array dimensions for PHP variable
     *
     * Returns zero if variable is scalar. This method is mostly needed for
     * correct arrays conversion.
     *
     * @return int
     */
    public function dimensions(): int;
}
