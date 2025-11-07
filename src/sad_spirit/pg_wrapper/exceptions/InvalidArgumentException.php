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

use sad_spirit\pg_wrapper\Exception;

/**
 * Namespaced version of SPL's InvalidArgumentException
 */
class InvalidArgumentException extends \InvalidArgumentException implements Exception
{
    use Stringifier;

    /**
     * Thrown when a method expects a value of several possible types but is given something else
     *
     * In case of single possible type a type hint should be used, obviously
     */
    public static function unexpectedType(string $method, string $expected, mixed $given): self
    {
        return new self(\sprintf(
            '%s() expects %s, %s given',
            $method,
            $expected,
            self::stringify($given)
        ));
    }

    /**
     * Formats qualified type name (for usage in exception messages)
     */
    public static function formatQualifiedName(string $typeName, ?string $schemaName): string
    {
        return (null === $schemaName ? '' : '"' . \strtr($schemaName, ['"' => '""']) . '".')
               . '"' . \strtr($typeName, ['"' => '""']) . '"';
    }
}
