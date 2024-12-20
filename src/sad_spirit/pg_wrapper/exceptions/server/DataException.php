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

namespace sad_spirit\pg_wrapper\exceptions\server;

use sad_spirit\pg_wrapper\exceptions\ServerException;

/**
 * Thrown when there are problems with processed data, like division by zero or numeric value out of range
 */
class DataException extends ServerException
{
}
