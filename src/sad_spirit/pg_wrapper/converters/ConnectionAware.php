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

use sad_spirit\pg_wrapper\Connection;

/**
 * Interface for classes depending on a Connection
 *
 * This is implemented by e.g. date and time converters to check connected server's DateStyle setting
 */
interface ConnectionAware
{
    /**
     * Sets the connection
     */
    public function setConnection(Connection $connection): void;
}
