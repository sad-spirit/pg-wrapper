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

namespace sad_spirit\pg_wrapper\tests\types;

/**
 * This should NOT work with EnumConverter
 */
enum IntBackedEnum: int
{
    case ONE = 1;
    case TWO = 2;
    case THREE = 3;
}
