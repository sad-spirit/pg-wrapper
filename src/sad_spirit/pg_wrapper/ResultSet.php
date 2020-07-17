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
    private $resource;

    /**
     * Factory for database type converters (mostly needed for setType())
     * @var TypeConverterFactory
     */
    private $converterFactory;

    /**
     * Type converters, indexed by column number
     * @var TypeConverter[]
     */
    private $converters = [];

    /**
     * Number of rows in result
     * @var int
     */
    private $numRows;

    /**
     * Number of columns in result
     * @var int
     */
    private $numFields;

    /**
     * Hash (column name => column number)
     * @var array
     */
    private $namesHash = [];

    /**
     * Current iterator position
     * @var int
     */
    private $position = 0;

    /**
     * @var int
     */
    private $mode = PGSQL_ASSOC;

    /**
     * Constructor.
     *
     * @param resource    $resource SQL result resource.
     * @param TypeConverterFactory $factory
     * @param array       $types    Types information, used to convert output values (overrides auto-generated types).
     * @throws exceptions\InvalidArgumentException
     */
    protected function __construct($resource, TypeConverterFactory $factory, array $types = [])
    {
        $this->resource         = $resource;
        $this->converterFactory = $factory;
        $this->numRows          = pg_num_rows($this->resource);
        $this->numFields        = pg_num_fields($this->resource);

        $oids = [];
        for ($i = 0; $i < $this->numFields; $i++) {
            $this->namesHash[pg_field_name($this->resource, $i)] = $i;
            $oids[$i] = pg_field_type_oid($this->resource, $i);
        }

        // first set the explicitly given types...
        foreach ($types as $index => $type) {
            $this->setType($index, $type);
        }

        // ...then use type factory to create default converters
        for ($i = 0; $i < $this->numFields; $i++) {
            if (!isset($this->converters[$i])) {
                $this->converters[$i] = $this->converterFactory->getConverterForTypeOID($oids[$i]);
            }
        }
    }

    /**
     * Creates a return value for various execute*() methods from underlying query result resource
     *
     * @param resource|bool $resource   SQL result resource, false if query failed.
     * @param Connection    $connection Connection, origin of result resource.
     * @param array         $types      Types information, used to convert output values (overrides auto-generated types).
     * @return bool|int|self
     */
    public static function createFromResultResource($resource, Connection $connection, array $types = [])
    {
        if (!$resource) {
            throw exceptions\ServerException::fromConnection($connection->getResource());
        } elseif (!is_resource($resource) || 'pgsql result' !== get_resource_type($resource)) {
            throw exceptions\InvalidArgumentException::unexpectedType(
                __METHOD__,
                'a query result resource',
                $resource
            );
        }

        switch (pg_result_status($resource)) {
            case PGSQL_COPY_IN:
            case PGSQL_COPY_OUT:
                pg_free_result($resource);
                return true;

            case PGSQL_COMMAND_OK:
                $count = pg_affected_rows($resource);
                pg_free_result($resource);
                return $count;

            case PGSQL_TUPLES_OK:
            default:
                return new self($resource, $connection->getTypeConverterFactory(), $types);
        }
    }

    /**
     * Explicitly sets the type converter for the result field
     *
     * @param int|string $fieldIndex Either field number or field name
     * @param mixed      $type       Either an instance of TypeConverter or an
     *                               argument for TypeConverterFactory::getConverterForTypeSpecification()
     * @return $this
     * @throws exceptions\InvalidArgumentException
     * @throws exceptions\OutOfBoundsException
     */
    public function setType($fieldIndex, $type): self
    {
        $this->checkFieldIndex($fieldIndex);
        if (is_string($fieldIndex)) {
            $fieldIndex = $this->namesHash[$fieldIndex];
        }

        $this->converters[$fieldIndex] = $this->converterFactory->getConverterForTypeSpecification($type);

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
    public function setMode(int $mode = PGSQL_ASSOC): self
    {
        if (PGSQL_ASSOC !== $mode && PGSQL_NUM !== $mode) {
            throw new exceptions\InvalidArgumentException(
                __METHOD__ . ' accepts either of PGSQL_ASSOC or PGSQL_NUM constants'
            );
        }
        $this->mode = $mode;

        return $this;
    }

    /**
     * Returns an array containing all values from a given column in the result set
     *
     * @param string|int $fieldIndex Either a column name or an index (0-based)
     * @return array
     * @throws exceptions\InvalidArgumentException
     * @throws exceptions\OutOfBoundsException
     */
    public function fetchColumn($fieldIndex): array
    {
        $this->checkFieldIndex($fieldIndex);
        if (is_string($fieldIndex)) {
            $fieldIndex = $this->namesHash[$fieldIndex];
        }

        $result = [];
        for ($i = 0; $i < $this->numRows; $i++) {
            $result[] = $this->converters[$fieldIndex]->input(
                pg_fetch_result($this->resource, $i, $fieldIndex)
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
     * @throws exceptions\OutOfBoundsException
     */
    public function fetchAll(?int $mode = null, $keyColumn = null, bool $forceArray = false, bool $group = false)
    {
        if (null !== $mode) {
            $oldMode = $this->mode;
            $this->setMode($mode);
        }
        if (null !== $keyColumn) {
            if ($this->numFields < 2) {
                throw new exceptions\OutOfBoundsException(
                    __METHOD__ . ': at least two columns needed for associative array result'
                );
            }
            $this->checkFieldIndex($keyColumn);
            if (PGSQL_ASSOC === $this->mode && ctype_digit((string)$keyColumn)) {
                $keyColumn = pg_field_name($this->resource, $keyColumn);
            } elseif (PGSQL_NUM === $this->mode && !ctype_digit((string)$keyColumn)) {
                $keyColumn = $this->namesHash[$keyColumn];
            }
        }
        $killArray = (!$forceArray && 2 === $this->numFields);

        $result = [];

        for ($i = 0; $i < $this->numRows; $i++) {
            $row = $this->read($i);
            if (null === $keyColumn) {
                $result[] = $row;

            } else {
                if (PGSQL_ASSOC === $this->mode) {
                    $key = $row[$keyColumn];
                    unset($row[$keyColumn]);
                } else {
                    list($key) = array_splice($row, $keyColumn, 1, []);
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
        return array_flip($this->namesHash);
    }

    /**
     * Returns the number of fields in the result
     *
     * @return int
     */
    public function getFieldCount()
    {
        return $this->numFields;
    }

    /**
     * Destructor. Frees the result resource.
     */
    public function __destruct()
    {
        if (isset($this->resource)) {
            pg_free_result($this->resource);
        }
    }

    /**
     * Prevents cloning the ResultSet object
     */
    private function __clone()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function current()
    {
        return $this->valid() ? $this->read($this->position) : false;
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        $this->position++;
    }

    /**
     * {@inheritdoc}
     */
    public function key()
    {
        return $this->position;
    }

    /**
     * {@inheritdoc}
     */
    public function valid()
    {
        return ($this->position >= 0) && ($this->position < $this->numRows);
    }

    /**
     * {@inheritdoc}
     */
    public function rewind()
    {
        $this->position = 0;
    }

    /**
     * Method defined in Countable interface
     *
     * @return int
     */
    public function count()
    {
        return $this->numRows;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset)
    {
        return ctype_digit((string)$offset) && $offset < $this->numRows;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset)
    {
        return $this->offsetExists($offset) ? $this->read($offset) : false;
    }

    /**
     * Disallows setting the offset
     *
     * @param mixed $offset (not used)
     * @param mixed $value  (not used)
     * @throws exceptions\BadMethodCallException
     */
    public function offsetSet($offset, $value)
    {
        throw new exceptions\BadMethodCallException(__CLASS__ . ' is read-only');
    }

    /**
     * Disallows unsetting the offset
     *
     * @param mixed $offset (not used)
     * @throws exceptions\BadMethodCallException
     */
    public function offsetUnset($offset)
    {
        throw new exceptions\BadMethodCallException(__CLASS__ . ' is read-only');
    }

    /**
     * Sanity check for field index
     *
     * @param string|int $fieldIndex
     * @throws exceptions\InvalidArgumentException
     * @throws exceptions\OutOfBoundsException
     */
    private function checkFieldIndex($fieldIndex): void
    {
        if (ctype_digit((string)$fieldIndex)) {
            if ($fieldIndex < 0 || $fieldIndex >= $this->numFields) {
                throw new exceptions\OutOfBoundsException(sprintf(
                    "%s: field number %d is not within range 0..%d",
                    __METHOD__,
                    $fieldIndex,
                    $this->numFields - 1
                ));
            }

        } elseif (is_string($fieldIndex)) {
            if (!isset($this->namesHash[$fieldIndex])) {
                throw new exceptions\OutOfBoundsException(
                    sprintf("%s: field name '%s' is not present", __METHOD__, $fieldIndex)
                );
            }

        } else {
            throw exceptions\InvalidArgumentException::unexpectedType(
                __METHOD__,
                'a field number or a field name',
                $fieldIndex
            );
        }
    }

    /**
     * Retrieves the row from result and performs type conversion on it
     *
     * @param int $position row number
     * @return array
     */
    private function read(int $position): array
    {
        $row = pg_fetch_array($this->resource, $position, $this->mode);
        foreach ($row as $key => &$value) {
            if (PGSQL_ASSOC === $this->mode) {
                $value = $this->converters[$this->namesHash[$key]]->input($value);
            } else {
                $value = $this->converters[$key]->input($value);
            }
        }
        return $row;
    }
}
