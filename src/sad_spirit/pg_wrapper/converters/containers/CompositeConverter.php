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

use sad_spirit\pg_wrapper\TypeConverter,
    sad_spirit\pg_wrapper\exceptions\InvalidArgumentException,
    sad_spirit\pg_wrapper\exceptions\TypeConversionException,
    sad_spirit\pg_wrapper\converters\ContainerConverter;

/**
 * Class for composite types (row types)
 */
class CompositeConverter extends ContainerConverter
{
    /**
     * Converters for fields within composite type
     * @var TypeConverter[]
     */
    private $_items = array();

    /**
     * Unlike hstore and array, composite types use doubled "" for escaping "
     * @var array
     */
    private $_escapes = array(
        '"'  => '""',
        '\\' => '\\\\'
    );

    /**
     * Constructor, accepts an array of (field name => field type converter)
     *
     * @param  array $items
     * @throws InvalidArgumentException
     */
    public function __construct(array $items)
    {
        if (!count($items)) {
            throw new InvalidArgumentException(
                __CLASS__ . " expects an array of Type instances, empty array given"
            );
        }
        foreach ($items as $field => $item) {
            if (!$item instanceof TypeConverter) {
                throw new InvalidArgumentException(sprintf(
                    "%s expects an array of Type instances, '%s' given for index '%s'",
                    __CLASS__, is_object($item) ? get_class($item) : gettype($item), $field
                ));
            }
            $this->_items[$field] = $item;
        }
    }

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
        foreach ($this->_items as $field => $type) {
            $v       = $type->output(isset($value[$field]) ? $value[$field] : null);
            $parts[] = ($v === null) ? '' : ('"' . strtr($v, $this->_escapes) . '"');
        }
        return '(' . join(',', $parts) . ')';
    }

    protected function parseInput($native, &$pos)
    {
        reset($this->_items);

        $result   = array();
        $unescape = array_flip($this->_escapes);

        $this->expectChar($native, $pos, '('); // Leading "("

        while (true) {
            /* @var $type TypeConverter */
            if (!(list($field, $type) = each($this->_items))) { // Check if we have more fields left.
                throw TypeConversionException::parsingFailed($this, 'end of input: no more fields left', $native, $pos);
            }

            switch ($char = $this->nextChar($native, $pos)) {
            case ',':
            case ')': // Comma or end of row instead of value: treat as NULL.
                $result[$field] = null;
                break;

            case '"': // Quoted string.
                if (!preg_match('/"((?>[^"]+|"")*)"/As', $native, $m, 0, $pos)) {
                    throw TypeConversionException::parsingFailed($this, 'quoted string', $native, $pos);
                }
                $result[$field]  = $type->input(strtr($m[1], $unescape));
                $pos            += call_user_func(self::$strlen, $m[0]);
                $char            = $this->nextChar($native, $pos);
                break;

            default: // Unquoted string.
                $len             = strcspn($native, ',)', $pos);
                $result[$field]  = $type->input(call_user_func(self::$substr, $native, $pos, $len));
                $pos            += $len;
                $char            = $this->nextChar($native, $pos);
                break;
            }

            switch ($char) { // Expect delimiter after value
            case ')':
                $pos++;
                break 2;
            case ',':
                $pos++;
                break;
            default:
                throw TypeConversionException::parsingFailed($this, "',' or ')'", $native, $pos);
            }
        }

        if (list($field,) = each($this->_items)) {
            // point error at preceding ')'
            throw TypeConversionException::parsingFailed($this, "value for '{$field}'", $native, $pos - 1);
        }

        return $result;
    }
}
