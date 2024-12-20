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

namespace sad_spirit\pg_wrapper\exceptions;

/**
 * Contains helper method to build a string representation of value
 */
trait Stringifier
{
    /**
     * Returns a string representation of $value for exception message
     */
    protected static function stringify(mixed $value): string
    {
        if (\is_object($value)) {
            return 'Object(' . $value::class . ')';

        } elseif (\is_array($value)) {
            $strings = [];
            foreach ($value as $k => $v) {
                $strings[] = \sprintf('%s => %s', $k, self::stringify($v));
            }
            return 'Array(' . \implode(', ', $strings) . ')';

        } elseif (\is_resource($value)) {
            return 'Resource (' . \get_resource_type($value) . ')';

        } elseif (\is_null($value)) {
            return 'null';

        } elseif (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return "'" . $value . "'";
    }
}
