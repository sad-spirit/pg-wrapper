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

use sad_spirit\pg_wrapper\{
    TypeConverter,
    exceptions\TypeConversionException
};

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

    /**
     * Gets next non-whitespace character from input
     *
     * @param string $str Input string
     * @param int    $p   Position within input string
     * @return string|null
     */
    protected function nextChar(string $str, int &$p): ?string
    {
        $p += \strspn($str, self::WHITESPACE, $p);
        return $str[$p] ?? null;
    }

    /**
     * Throws an Exception if next non-whitespace character in input is not the given char
     *
     * @param string $string
     * @param int    $pos
     * @param string $char
     * @throws TypeConversionException
     */
    protected function expectChar(string $string, int &$pos, string $char): void
    {
        if ($char !== $this->nextChar($string, $pos)) {
            throw TypeConversionException::parsingFailed($this, "'" . $char . "'", $string, $pos);
        }
        $pos++;
    }
}
