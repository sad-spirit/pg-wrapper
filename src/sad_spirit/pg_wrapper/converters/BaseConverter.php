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

use sad_spirit\pg_wrapper\{
    TypeConverter,
    exceptions\TypeConversionException
};

/**
 * Base class for type converters, defines handling for null values and some parsing helpers
 */
abstract class BaseConverter implements TypeConverter
{
    public function input(?string $native)
    {
        return ($native === null) ? null : $this->inputNotNull($native);
    }

    public function output($value): ?string
    {
        return ($value === null) ? null : $this->outputNotNull($value);
    }

    public function dimensions(): int
    {
        return 0;
    }

    /**
     * Parses native value that is not NULL into PHP variable
     *
     * @param string $native
     * @return mixed
     */
    abstract protected function inputNotNull(string $native);

    /**
     * Converts PHP variable not identical to null into native format
     *
     * @param mixed $value
     * @return string
     */
    abstract protected function outputNotNull($value): string;

    /**
     * Gets next non-whitespace character from input
     *
     * @param string $str Input string
     * @param int    $p   Position within input string
     * @return string|null
     */
    protected function nextChar(string $str, int &$p): ?string
    {
        $p += strspn($str, " \t\r\n", $p);
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

    /**
     * Returns a part of string satisfying the strspn() mask
     *
     * @param string $subject Input string
     * @param string $mask    Mask for strspn()
     * @param int    $start   Position in the input string, will be moved
     * @return string
     * @deprecated Since 2.5.0 - the method is unused in the package and will be removed in the next major version
     */
    protected function getStrspn(string $subject, string $mask, int &$start): string
    {
        $length = strspn($subject, $mask, $start);
        $masked = substr($subject, $start, $length);
        $start += $length;

        return $masked;
    }
}
