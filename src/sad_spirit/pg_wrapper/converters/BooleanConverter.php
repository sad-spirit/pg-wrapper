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
 * Converter for boolean type
 */
class BooleanConverter extends BaseConverter
{
    protected function inputNotNull(string $native): bool
    {
        $native = \trim($native, self::WHITESPACE);
        return !($native === 'false' || $native === 'f' || $native === '0' || $native === '');
    }

    protected function outputNotNull(mixed $value): string
    {
        return (!$value || $value === 'false' || $value === 'f') ? 'f' : 't';
    }
}
