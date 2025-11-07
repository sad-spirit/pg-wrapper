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

use sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Base class for types containing some other types
 */
abstract class ContainerConverter extends BaseConverter
{
    /**
     * Gets next non-whitespace character from input
     *
     * @param string $str Input string
     * @param int    $p   Position within input string
     */
    protected function nextChar(string $str, int &$p): ?string
    {
        $p += \strspn($str, self::WHITESPACE, $p);
        return $str[$p] ?? null;
    }

    /**
     * Throws an Exception if next non-whitespace character in input is not the given char
     *
     * @throws TypeConversionException
     */
    protected function expectChar(string $string, int &$pos, string $char): void
    {
        if ($char !== $this->nextChar($string, $pos)) {
            throw TypeConversionException::parsingFailed($this, "'" . $char . "'", $string, $pos);
        }
        $pos++;
    }

    protected function inputNotNull(string $native): mixed
    {
        $pos   = 0;
        $value = $this->parseInput($native, $pos);
        if (null !== $this->nextChar($native, $pos)) {
            throw TypeConversionException::parsingFailed($this, 'end of input', $native, $pos);
        }
        return $value;
    }

    /**
     * Parses a native value into PHP variable from given position
     */
    abstract protected function parseInput(string $native, int &$pos): mixed;
}
