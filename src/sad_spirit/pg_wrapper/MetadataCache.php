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
 * @copyright 2014 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

namespace sad_spirit\pg_wrapper;

/**
 * Interface for cache used to store DB metadata
 */
interface MetadataCache
{
    /**
     * Stores an item in cache
     *
     * @param string $key Name of the cached data.
     * @param mixed $value Data to save.
     */
    public function setItem($key, $value);

    /**
     * Gets an item from cache
     *
     * @param string $key Name of the cached data.
     * @return mixed Saved data or null if not found.
     */
    public function getItem($key);

    /**
     * Clears cache entries that start with $prefix
     *
     * Can be used to remove all cached items corresponding to a certain
     * database connection identifier
     *
     * @param string $prefix
     */
    public function clearByPrefix($prefix);
}
