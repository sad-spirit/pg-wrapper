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
 * @copyright 2014-2024 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

declare(strict_types=1);

namespace sad_spirit\pg_wrapper\converters;

/**
 * Interface for classes (presumably converter factories) containing an instance of TypeOIDMapper
 *
 * @since 2.2.0
 */
interface TypeOIDMapperAware
{
    /**
     * Sets the mapper instance
     */
    public function setOIDMapper(TypeOIDMapper $mapper): void;

    /**
     * Returns the mapper instance
     */
    public function getOIDMapper(): TypeOIDMapper;
}
