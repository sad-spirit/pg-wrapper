<?php

/*
 * This file is part of sad_spirit/pg_wrapper:
 * converter of complex PostgreSQL types and an OO wrapper for PHP's pgsql extension.
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
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
