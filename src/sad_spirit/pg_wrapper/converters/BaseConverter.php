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

use sad_spirit\pg_wrapper\TypeConverter;

/**
 * Base class for type converters, defines handling for null values and some parsing helpers
 */
abstract class BaseConverter implements TypeConverter
{
    /**
     * Symbols that should be considered whitespace by type converters
     * https://git.postgresql.org/gitweb/?p=postgresql.git;a=commitdiff;h=ae6d06f09684d8f8a7084514c9b35a274babca61
     */
    public const WHITESPACE = " \n\r\t\v\f";

    public function input(?string $native): mixed
    {
        return ($native === null) ? null : $this->inputNotNull($native);
    }

    public function output(mixed $value): ?string
    {
        return ($value === null) ? null : $this->outputNotNull($value);
    }

    public function dimensions(): int
    {
        return 0;
    }

    /**
     * Parses native value that is not NULL into PHP variable
     */
    abstract protected function inputNotNull(string $native): mixed;

    /**
     * Converts PHP variable not identical to null into native format
     */
    abstract protected function outputNotNull(mixed $value): string;
}
