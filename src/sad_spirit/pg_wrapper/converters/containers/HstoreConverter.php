<?php

/**
 * Wrapper for PHP's pgsql extension providing conversion of complex DB types
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-wrapper/master/LICENSE
 *
 * @package   sad_spirit\pg_wrapper
 * @copyright 2014-2020 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
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

    protected function outputNotNull($value): string
    {
        if (is_object($value)) {
            $value = (array)$value;
        } elseif (!is_array($value)) {
            throw TypeConversionException::unexpectedValue($this, 'output', 'array or object', $value);
        }
        $parts = [];
        foreach ($value as $key => $item) {
            $parts[] =  '"' . addcslashes((string)$key, "\"\\") . '"'
                        . '=>'
                        . (($item === null) ? 'NULL' : '"' . addcslashes((string)$item, "\"\\") . '"');
        }
        return implode(', ', $parts);
    }

    /**
     * Reads a (quoted or unquoted) string from input
     *
     * @param string $string
     * @param int    $pos
     * @param string $delimiters  Delimiters for unquoted string
     * @param bool   $convertNull Whether to convert unquoted string 'null' to null
     * @return null|string
     * @throws TypeConversionException
     */
    private function readString(string $string, int &$pos, string $delimiters, bool $convertNull): ?string
    {
        if ('"' === $string[$pos]) {
            if (!preg_match('/"((?>[^"\\\\]+|\\\\.)*)"/As', $string, $m, 0, $pos)) {
                throw TypeConversionException::parsingFailed($this, 'quoted string', $string, $pos);
            }
            $pos += strlen($m[0]);
            return stripcslashes($m[1]);

        } else {
            $length  = strcspn($string, " \t\r\n" . $delimiters, $pos);
            $value   = substr($string, $pos, $length);
            $pos    += $length;
            if ($convertNull && 0 === strcasecmp($value, 'NULL')) {
                return null;
            } else {
                return stripcslashes($value);
            }
        }
    }

    protected function parseInput(string $native, int &$pos): array
    {
        $result = [];

        while (null !== ($char = $this->nextChar($native, $pos))) {
            $key = $this->readString($native, $pos, '=', false);

            $this->expectChar($native, $pos, '=');
            // don't use expectChar as there can be no whitespace
            if ('>' !== $native[$pos]) {
                throw TypeConversionException::parsingFailed($this, "'=>'", $native, $pos - 1);
            }
            $pos++;
            // skip possible whitespace before value
            $this->nextChar($native, $pos);

            $result[$key] = $this->readString($native, $pos, ',', true);

            // skip one comma after the pair
            if (',' === $this->nextChar($native, $pos)) {
                $pos++;
            }
        }

        return $result;
    }
}
