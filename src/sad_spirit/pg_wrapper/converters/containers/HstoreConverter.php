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

namespace sad_spirit\pg_wrapper\converters\containers;

use sad_spirit\pg_wrapper\{
    exceptions\TypeConversionException,
    converters\ContainerConverter
};

/**
 * Class for hstore type from contrib/hstore, representing a set of key => value pairs
 */
class HstoreConverter extends ContainerConverter
{
    public function dimensions(): int
    {
        return 1;
    }

    protected function outputNotNull(mixed $value): string
    {
        if (\is_object($value)) {
            $value = (array)$value;
        } elseif (!\is_array($value)) {
            throw TypeConversionException::unexpectedValue($this, 'output', 'array or object', $value);
        }
        $parts = [];
        foreach ($value as $key => $item) {
            $parts[] =  '"' . \addcslashes((string)$key, '"\\') . '"'
                        . '=>'
                        . (($item === null) ? 'NULL' : '"' . \addcslashes((string)$item, '"\\') . '"');
        }
        return \implode(', ', $parts);
    }

    /**
     * Reads a quoted string from input
     *
     * @param string $string
     * @param int    $pos
     * @return string
     * @throws TypeConversionException in case of unterminated string
     */
    private function readQuoted(string $string, int &$pos): string
    {
        if (!\preg_match('/"((?>[^"\\\\]+|\\\\.)*)"/As', $string, $m, 0, $pos)) {
            throw TypeConversionException::parsingFailed($this, 'quoted string', $string, $pos);
        }
        $pos += \strlen($m[0]);
        return \stripcslashes($m[1]);
    }

    /**
     * Reads an unquoted string from input
     *
     * @param string $string
     * @param int    $pos
     * @param string $delimiter Delimiter for a string, either '=' or ','
     * @return string
     * @throws TypeConversionException in case of empty string, those should always be quoted
     */
    private function readUnquoted(string $string, int &$pos, string $delimiter): string
    {
        if (0 === ($length = \strcspn($string, self::WHITESPACE . $delimiter, $pos))) {
            throw TypeConversionException::parsingFailed($this, 'unquoted string', $string, $pos);
        }
        $value  = \substr($string, $pos, $length);
        $pos   += $length;

        return $value;
    }

    /**
     * Reads a hstore key from input
     *
     * @param string $string
     * @param int    $pos
     * @return string
     */
    private function readKey(string $string, int &$pos): string
    {
        if ('"' === $string[$pos]) {
            return $this->readQuoted($string, $pos);
        } else {
            return \stripcslashes($this->readUnquoted($string, $pos, '='));
        }
    }

    /**
     * Reads a hstore value from input
     *
     * This converts an unquoted string 'null' to an actual null value
     *
     * @param string $string
     * @param int    $pos
     * @return string|null
     */
    private function readValue(string $string, int &$pos): ?string
    {
        if ('"' === $string[$pos]) {
            return $this->readQuoted($string, $pos);
        } else {
            $value = $this->readUnquoted($string, $pos, ',');
            return 0 === \strcasecmp($value, 'NULL') ? null : \stripcslashes($value);
        }
    }

    /**
     * {@inheritDoc}
     * @return array<string,?string>
     */
    protected function parseInput(string $native, int &$pos): array
    {
        $result = [];

        while (null !== $this->nextChar($native, $pos)) {
            $key = $this->readKey($native, $pos);

            $this->expectChar($native, $pos, '=');
            // don't use expectChar as there can be no whitespace
            if ('>' !== $native[$pos]) {
                throw TypeConversionException::parsingFailed($this, "'=>'", $native, $pos - 1);
            }
            $pos++;
            // skip possible whitespace before value
            if (null === $this->nextChar($native, $pos)) {
                throw TypeConversionException::parsingFailed($this, 'value', $native, $pos - 1);
            }

            $result[$key] = $this->readValue($native, $pos);

            // skip one comma after the pair
            if (',' === $this->nextChar($native, $pos)) {
                $pos++;
            }
        }

        return $result;
    }
}
