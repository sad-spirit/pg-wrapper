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
 * @copyright 2014 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

namespace sad_spirit\pg_wrapper\converters\containers;

use sad_spirit\pg_wrapper\exceptions\TypeConversionException,
    sad_spirit\pg_wrapper\converters\ContainerConverter;

/**
 * Class for hstore type from contrib/hstore, representing a set of key => value pairs
 */
class HstoreConverter extends ContainerConverter
{
    public function dimensions()
    {
        return 1;
    }

    protected function outputNotNull($value)
    {
        if (is_object($value)) {
            $value = (array)$value;
        } elseif (!is_array($value)) {
            throw TypeConversionException::unexpectedValue($this, 'output', 'array or object', $value);
        }
        $parts = array();
        foreach ($value as $key => $item) {
            $parts[] =  '"' . addcslashes($key, "\"\\") . '"'
                        . '=>'
                        . (($item === null) ? 'NULL' : '"' . addcslashes($item, "\"\\") . '"');
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
    private function _readString($string, &$pos, $delimiters, $convertNull)
    {
        if ('"' === $string[$pos]) {
            if (!preg_match('/"((?>[^"\\\\]+|\\\\.)*)"/As', $string, $m, 0, $pos)) {
                throw TypeConversionException::parsingFailed($this, 'quoted string', $string, $pos);
            }
            $pos += call_user_func(self::$strlen, $m[0]);
            return stripcslashes($m[1]);

        } else {
            $length  = strcspn($string, " \t\r\n" . $delimiters, $pos);
            $value   = call_user_func(self::$substr, $string, $pos, $length);
            $pos    += $length;
            if ($convertNull && 0 === strcasecmp($value, 'NULL')) {
                return null;
            } else {
                return stripcslashes($value);
            }
        }
    }

    protected function parseInput($native, &$pos)
    {
        $result = array();

        while (false !== ($char = $this->nextChar($native, $pos))) {
            $key = $this->_readString($native, $pos, '=', false);

            $this->expectChar($native, $pos, '=');
            // don't use expectChar as there can be no whitespace
            if ('>' !== $native[$pos]) {
                throw TypeConversionException::parsingFailed($this, "'=>'", $native, $pos - 1);
            }
            $pos++;
            // skip possible whitespace before value
            $this->nextChar($native, $pos);

            $result[$key] = $this->_readString($native, $pos, ',', true);

            // skip one comma after the pair
            if (',' === $this->nextChar($native, $pos)) {
                $pos++;
            }
        }

        return $result;
    }
}
