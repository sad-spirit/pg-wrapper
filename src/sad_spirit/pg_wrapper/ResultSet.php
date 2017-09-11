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
 * @copyright 2014-2017 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

namespace sad_spirit\pg_wrapper;

/**
 * Class representing a query result
 */
class ResultSet implements \Iterator, \Countable, \ArrayAccess
{
    /**
     * PostgreSQL result resource
     * @var resource
     */
    private $_resource;

    /**
     * Factory for database type converters (mostly needed for setType())
     * @var TypeConverterFactory
     */
    private $_converterFactory;

    /**
     * Type converters, indexed by column number
     * @var TypeConverter[]
     */
    private $_converters = array();

    /**
     * Number of rows in result
     * @var int
     */
    private $_numRows;

    /**
     * Number of columns in result
     * @var int
     */
    private $_numFields;

    /**
     * Hash (column name => column number)
     * @var array
     */
    private $_namesHash = array();

    /**
     * Current iterator position
     * @var int
     */
    private $_position = 0;

    /**
     * @var int
     */
    private $_mode = PGSQL_ASSOC;

    /**
     * Constructor.
     *
     * @param resource    $resource SQL result resource.
     * @param TypeConverterFactory $factory
     * @param array       $types    Types information, used to convert output values (overrides auto-generated types).
     * @throws exceptions\InvalidArgumentException
     */
    public function __construct($resource, TypeConverterFactory $factory, array $types = array())
    {
        if (!is_resource($resource) || 'pgsql result' !== get_resource_type($resource)) {
            throw new exceptions\InvalidArgumentException(sprintf(
                "%s requires a query result resource, '%s' given", __CLASS__,
                is_resource($resource) ? 'resource(' . get_resource_type($resource) . ')' : gettype($resource)
            ));
        }
        $this->_resource         = $resource;
        $this->_converterFactory = $factory;

        $this->_numRows     = pg_num_rows($this->_resource);
        $this->_numFields   = pg_num_fields($this->_resource);

        $oids = array();
        for ($i = 0; $i < $this->_numFields; $i++) {
            $this->_namesHash[pg_field_name($this->_resource, $i)] = $i;
            $oids[$i] = pg_field_type_oid($this->_resource, $i);
        }

        // first set the explicitly given types...
        foreach ($types as $index => $type) {
            $this->setType($index, $type);
        }

        // ...then use type factory to create default converters
        for ($i = 0; $i < $this->_numFields; $i++) {
            if (!isset($this->_converters[$i])) {
                $this->_converters[$i] = $this->_converterFactory->getConverter($oids[$i]);
            }
        }
    }

    /**
     * Explicitly sets the type converter for the result field
     *
     * @param int|string $fieldIndex Either field number or field name
     * @param mixed      $type       Either an instance of TypeConverter or an
     *                               argument for TypeConverterFactory::getConverter()
     * @return $this
     * @throws exceptions\InvalidArgumentException
     */
    public function setType($fieldIndex, $type)
    {
        $this->_checkFieldIndex($fieldIndex);
        if (is_string($fieldIndex)) {
            $fieldIndex = $this->_namesHash[$fieldIndex];
        }

        $this->_converters[$fieldIndex] = $this->_converterFactory->getConverter($type);

        return $this;
    }

    /**
     * Sets how the returned rows are indexed
     *
     * @param int $mode either PGSQL_ASSOC or PGSQL_NUM constants. PGSQL_BOTH is not
     *                  accepted, since it will lead to double the type conversion
     *                  work for questionable benefits.
     * @return $this
     * @throws exceptions\InvalidArgumentException
     */
    public function setMode($mode = PGSQL_ASSOC)
    {
        if (PGSQL_ASSOC !== $mode && PGSQL_NUM !== $mode) {
            throw new exceptions\InvalidArgumentException(
                __METHOD__ . ' accepts either of PGSQL_ASSOC or PGSQL_NUM constants'
            );
        }
        $this->_mode = $mode;

        return $this;
    }

    /**
     * Returns an array containing all values from a given column in the result set
     *
     * @param string|int $fieldIndex Either a column name or an index (0-based)
     * @return array
     * @throws exceptions\InvalidArgumentException
     */
    public function fetchColumn($fieldIndex)
    {
        $this->_checkFieldIndex($fieldIndex);
        if (is_string($fieldIndex)) {
            $fieldIndex = $this->_namesHash[$fieldIndex];
        }

        $result = array();
        for ($i = 0; $i < $this->_numRows; $i++) {
            $result[] = $this->_converters[$fieldIndex]->input(
                pg_fetch_result($this->_resource, $i, $fieldIndex)
            );
        }
        return $result;
    }

    /**
     * Returns an array containing all rows of the result set
     *
     * @param null|int        $mode       Fetch mode, either PGSQL_ASSOC or PGSQL_NUM
     * @param string|int|null $keyColumn  Either a column name or an index (0-based).
     *        If given, values of this column will be used as keys in the outer array
     * @param bool            $forceArray Used only with $keyColumn when the query
     *        returns exactly two columns. If true the values will be one element arrays
     *        with other column's values, instead of values directly
     * @param bool            $group       If true, the values in the returned array are
     *        wrapped in another array. If there are duplicate values in key column, values
     *        of other columns will be appended to this array instead of overwriting previous ones
     * @return array
     * @throws exceptions\InvalidArgumentException
     */
    public function fetchAll($mode = null, $keyColumn = null, $forceArray = false, $group = false)
    {
        if (null !== $mode) {
            $oldMode = $this->_mode;
            $this->setMode($mode);
        }
        if (null !== $keyColumn) {
            if ($this->_numFields < 2) {
                throw new exceptions\InvalidArgumentException(
                    __METHOD__ . ': at least two columns needed for associative array result'
                );
            }
            $this->_checkFieldIndex($keyColumn);
            if (PGSQL_ASSOC === $this->_mode && ctype_digit((string)$keyColumn)) {
                $keyColumn = pg_field_name($this->_resource, $keyColumn);
            } elseif (PGSQL_NUM === $this->_mode && !ctype_digit((string)$keyColumn)) {
                $keyColumn = $this->_namesHash[$keyColumn];
            }
        }
        $killArray = (!$forceArray && 2 === $this->_numFields);

        $result = array();

        for ($i = 0; $i < $this->_numRows; $i++) {
            $row = $this->_read($i);
            if (null === $keyColumn) {
                $result[] = $row;

            } else {
                if (PGSQL_ASSOC === $this->_mode) {
                    $key = $row[$keyColumn];
                    unset($row[$keyColumn]);
                } else {
                    list($key) = array_splice($row, $keyColumn, 1, array());
                }
                if ($killArray) {
                    $row = reset($row);
                }
                if ($group) {
                    $result[$key][] = $row;
                } else {
                    $result[$key] = $row;
                }
            }
        }

        if (isset($oldMode)) {
            $this->setMode($oldMode);
        }

        return $result;
    }

    /**
     * Returns the names of fields in the result
     *
     * @return array
     */
    public function getFieldNames()
    {
        return array_flip($this->_namesHash);
    }

    /**
     * Returns the number of fields in the result
     *
     * @return int
     */
    public function getFieldCount()
    {
        return $this->_numFields;
    }

    /**
     * Destructor. Frees the result resource.
     */
    public function __destruct()
    {
        pg_free_result($this->_resource);
    }

    /**#@+
     * Methods defined in Iterator interface
     */
    public function current()
    {
        return $this->valid() ? $this->_read($this->_position) : false;
    }

    public function next()
    {
        $this->_position++;
    }

    public function key()
    {
        return $this->_position;
    }

    public function valid()
    {
        return ($this->_position >= 0) && ($this->_position < $this->_numRows);
    }

    public function rewind()
    {
        $this->_position = 0;
    }
    /**#@-*/

    /**
     * Method defined in Countable interface
     *
     * @return int
     */
    public function count()
    {
        return $this->_numRows;
    }

    /**#@+
     * Methods defined in ArrayAccess interface
     */
    public function offsetExists($offset)
    {
        return ctype_digit((string)$offset) && $offset < $this->_numRows;
    }

    public function offsetGet($offset)
    {
        return $this->offsetExists($offset) ? $this->_read($offset) : false;
    }

    public function offsetSet($offset, $value)
    {
        throw new exceptions\InvalidArgumentException(__CLASS__ . ' is _read-only');
    }

    public function offsetUnset($offset)
    {
        throw new exceptions\InvalidArgumentException(__CLASS__ . ' is _read-only');
    }
    /**#@-*/

    /**
     * Sanity check for field index
     *
     * @param string|int $fieldIndex
     * @throws exceptions\InvalidArgumentException
     */
    private function _checkFieldIndex($fieldIndex)
    {
        if (ctype_digit((string)$fieldIndex)) {
            if ($fieldIndex < 0 || $fieldIndex >= $this->_numFields) {
                throw new exceptions\InvalidArgumentException(sprintf(
                    "%s: field number %d is not within range 0..%d",
                    __METHOD__, $fieldIndex, $this->_numFields - 1
                ));
            }

        } elseif (is_string($fieldIndex)) {
            if (!isset($this->_namesHash[$fieldIndex])) {
                throw new exceptions\InvalidArgumentException(sprintf(
                    "%s: field name '%s' is not present", __METHOD__, $fieldIndex
                ));
            }

        } else {
            throw new exceptions\InvalidArgumentException(sprintf(
                "%s expects a field number or a field name, '%s' given",
                __METHOD__, is_object($fieldIndex) ? get_class($fieldIndex) : gettype($fieldIndex)
            ));
        }
    }

    /**
     * Retrieves the row from result and performs type conversion on it
     *
     * @param int $position row number
     * @return array
     */
    private function _read($position)
    {
        $row = pg_fetch_array($this->_resource, $position, $this->_mode);
        foreach ($row as $key => &$value) {
            if (PGSQL_ASSOC === $this->_mode) {
                $value = $this->_converters[$this->_namesHash[$key]]->input($value);
            } else {
                $value = $this->_converters[$key]->input($value);
            }
        }
        return $row;
    }
}
