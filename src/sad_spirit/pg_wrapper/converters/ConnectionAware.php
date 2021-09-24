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

namespace sad_spirit\pg_wrapper\converters;

/**
 * Interface for type converters whose behaviour may change according to connection properties
 *
 * Currently implemented by date and time converters to check server's DateStyle setting
 */
interface ConnectionAware
{
    /**
     * Sets the connection resource this converter works with
     *
     * @param resource|\Pgsql\Connection $resource
     * @return void
     */
    public function setConnectionResource($resource): void;
}
