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

namespace sad_spirit\pg_wrapper;

use PgSql\Result as NativeResult;

/**
 * Class representing a query result
 *
 * @implements \Iterator<int, array>
 * @implements \ArrayAccess<int, ?array>
 *
 * @phpstan-consistent-constructor
 */
class Result implements \Iterator, \Countable, \ArrayAccess
{
    /** PostgreSQL native result object */
    private NativeResult $native;

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
     * Number of affected rows for DML query
     * @var int
     */
    private $affectedRows;

    /**
     * Number of rows in result
     * @var int
     */
    private $numRows = 0;

    /**
     * Number of columns in result
     * @var int
     */
    private $numFields = 0;

    /**
     * Hash (column name => column number)
     * @var array<string, int>
     */
    private $namesHash = [];

    /**
     * Table OIDs, indexed by column number
     * @var array<int, int|numeric-string|null>
     */
    private $tableOIDs = [];

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
     * Arguments for last call to read() method
     * @var array{int,int}|null
     */
    private $lastReadParams = null;

    /**
     * Result of last read() call
     * @var array
     */
    private $lastReadResult;

    /**
     * Constructor.
     *
     * @param NativeResult         $native  SQL result object.
     * @param TypeConverterFactory $factory
     * @param array                $types   Types information, used to convert output values
     *                                      (overrides auto-generated types).
     * @throws exceptions\InvalidArgumentException
     * @throws exceptions\RuntimeException
     *
     * @psalm-suppress PossiblyInvalidArgument
     */
    protected function __construct(NativeResult $native, TypeConverterFactory $factory, array $types = [])
    {
        $this->native           = $native;
        $this->converterFactory = $factory;
        $this->affectedRows     = pg_affected_rows($this->native);

        if (PGSQL_TUPLES_OK === pg_result_status($this->native)) {
            $this->setupResultFields($types);
        }
    }

    /**
     * Returns the native object representing query result
     */
    protected function getNative(): NativeResult
    {
        return $this->native;
    }

    /**
     * Sets up type converters and field name -> field index mapping for results returning rows
     *
     * @param array $types Types information, used to convert output values (overrides auto-generated types).
     */
    private function setupResultFields(array $types): void
    {
        $native          = $this->getNative();
        $this->numRows   = pg_num_rows($native);
        $this->numFields = pg_num_fields($native);

        $OIDs = [];
        for ($i = 0; $i < $this->numFields; $i++) {
            $this->namesHash[pg_field_name($native, $i)] = $i;
            $this->tableOIDs[$i] = pg_field_table($native, $i, true) ?: null;
            $OIDs[$i] = pg_field_type_oid($native, $i);
        }

        // first set the explicitly given types...
        foreach ($types as $index => $type) {
            $this->setType($index, $type);
        }

        /** @var array<int|numeric-string> $OIDs */
        // ...then use type factory to create default converters
        for ($i = 0; $i < $this->numFields; $i++) {
            if (!isset($this->converters[$i])) {
                $this->converters[$i] = $this->converterFactory->getConverterForTypeOID($OIDs[$i]);
            }
        }
    }

    /**
     * Converts a return value of native query execution methods into either a ResultSet instance or an Exception
     *
     * @param false|NativeResult $returnValue Value returned by native query execution method.
     * @param Connection         $connection  Connection used to execute the query.
     * @param array              $types       Types information, used to convert output values
     *                                        (overrides auto-generated types).
     * @return static
     * @throws exceptions\InvalidArgumentException
     * @throws exceptions\RuntimeException
     * @throws exceptions\ServerException
     */
    public static function createFromReturnValue(
        false|NativeResult $returnValue,
        Connection $connection,
        array $types = []
    ): self {
        if (false === $returnValue) {
            throw exceptions\ServerException::fromConnection($connection);
        }
        // Methods we use in Connection and PreparedStatement (pg_query(), etc) can only return results
        // where status is one of PGSQL_COMMAND_OK, PGSQL_TUPLES_OK, PGSQL_COPY_OUT, PGSQL_COPY_IN.
        // All of these will allow at least pg_affected_rows()
        return new static($returnValue, $connection->getTypeConverterFactory(), $types);
    }

    /**
     * Returns number of rows affected by INSERT, UPDATE, and DELETE queries
     *
     * In case of SELECT queries this will be equal to what count() returns
     *
     * @return int
     */
    public function getAffectedRows(): int
    {
        return $this->affectedRows;
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
        $this->converters[$this->checkFieldIndex($fieldIndex)] =
            $this->converterFactory->getConverterForTypeSpecification($type);
        $this->lastReadParams = null;

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
        $fieldIndex = $this->checkFieldIndex($fieldIndex);

        $result = [];
        $native = $this->getNative();
        for ($i = 0; $i < $this->numRows; $i++) {
            $result[] = $this->converters[$fieldIndex]->input(pg_fetch_result($native, $i, $fieldIndex));
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
    public function fetchAll(?int $mode = null, $keyColumn = null, bool $forceArray = false, bool $group = false): array
    {
        if (null === $mode) {
            $mode = $this->mode;
        } elseif (PGSQL_ASSOC !== $mode && PGSQL_NUM !== $mode) {
            throw new exceptions\InvalidArgumentException(
                __METHOD__ . ' accepts either of PGSQL_ASSOC or PGSQL_NUM constants for $mode'
            );
        }

        if (null !== $keyColumn) {
            if ($this->numFields < 2) {
                throw new exceptions\OutOfBoundsException(
                    __METHOD__ . ': at least two columns needed for associative array result'
                );
            }
            $fieldIndex = $this->checkFieldIndex($keyColumn);
            if (PGSQL_NUM === $mode) {
                $keyColumn = $fieldIndex;
            } elseif (!is_string($keyColumn) || $keyColumn === (string)$fieldIndex) {
                $keyColumn = pg_field_name($this->getNative(), $fieldIndex);
            }
        }
        $killArray = (!$forceArray && 2 === $this->numFields);

        $result = [];

        for ($i = 0; $i < $this->numRows; $i++) {
            $row = $this->read($i, $mode);
            if (null === $keyColumn) {
                $result[] = $row;

            } else {
                if (!is_int($keyColumn)) {
                    $key = $row[$keyColumn];
                    unset($row[$keyColumn]);
                } else {
                    [$key] = array_splice($row, $keyColumn, 1, []);
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

        return $result;
    }

    /**
     * Returns the names of fields in the result
     *
     * @return string[]
     */
    public function getFieldNames(): array
    {
        return array_flip($this->namesHash);
    }

    /**
     * Returns the number of fields in the result
     *
     * @return int
     */
    public function getFieldCount(): int
    {
        return $this->numFields;
    }

    /**
     * Returns the OID for a table that contains the given result field
     *
     * Will return null if the field is e.g. a literal or a calculated value
     *
     * @param int|string $fieldIndex
     * @return int|numeric-string|null
     */
    public function getTableOID($fieldIndex)
    {
        return $this->tableOIDs[$this->checkFieldIndex($fieldIndex)];
    }

    /**
     * Destructor. Frees the result resource.
     */
    public function __destruct()
    {
        pg_free_result($this->getNative());
    }

    /**
     * Prevents cloning the ResultSet object
     */
    private function __clone()
    {
    }

    /**
     * {@inheritdoc}
     * @psalm-ignore-nullable-return
     */
    public function current(): ?array
    {
        return $this->valid() ? $this->read($this->position, $this->mode) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function next(): void
    {
        $this->position++;
    }

    /**
     * {@inheritdoc}
     */
    public function key(): int
    {
        return $this->position;
    }

    /**
     * {@inheritdoc}
     */
    public function valid(): bool
    {
        return ($this->position >= 0) && ($this->position < $this->numRows);
    }

    /**
     * {@inheritdoc}
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * Method defined in Countable interface
     *
     * @return int
     */
    public function count(): int
    {
        return $this->numRows;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset): bool
    {
        /** @psalm-suppress NoValue */
        if (is_string($offset) && ctype_digit($offset)) {
            /** @psalm-suppress InvalidCast */
            $offset = (int)$offset;
        } elseif (!is_int($offset)) {
            return false;
        }
        return $offset >= 0 && $offset < $this->numRows;
    }

    /**
     * {@inheritdoc}
     * @psalm-return array|null
     * @psalm-ignore-nullable-return
     */
    public function offsetGet($offset): ?array
    {
        return $this->offsetExists($offset) ? $this->read((int)$offset, $this->mode) : null;
    }

    /**
     * Disallows setting the offset
     *
     * @param mixed $offset (not used)
     * @param mixed $value  (not used)
     * @throws exceptions\BadMethodCallException
     */
    public function offsetSet($offset, $value): void
    {
        throw new exceptions\BadMethodCallException(__CLASS__ . ' is read-only');
    }

    /**
     * Disallows unsetting the offset
     *
     * @param mixed $offset (not used)
     * @throws exceptions\BadMethodCallException
     */
    public function offsetUnset($offset): void
    {
        throw new exceptions\BadMethodCallException(__CLASS__ . ' is read-only');
    }

    /**
     * Sanity check for field index
     *
     * @param string|int $fieldIndex
     * @return int Numeric index of field in result set
     * @throws exceptions\InvalidArgumentException
     * @throws exceptions\OutOfBoundsException
     */
    private function checkFieldIndex($fieldIndex): int
    {
        if (is_int($fieldIndex) || ctype_digit((string)$fieldIndex)) {
            if ($fieldIndex >= 0 && $fieldIndex < $this->numFields) {
                return (int)$fieldIndex;
            } else {
                throw new exceptions\OutOfBoundsException(sprintf(
                    "%s: field number %d is not within range 0..%d",
                    __METHOD__,
                    $fieldIndex,
                    $this->numFields - 1
                ));
            }

        } elseif (is_string($fieldIndex)) {
            if (isset($this->namesHash[$fieldIndex])) {
                return $this->namesHash[$fieldIndex];
            } else {
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
     * @param int $mode     fetch mode, either of PGSQL_ASSOC or PGSQL_NUM
     * @return array
     */
    private function read(int $position, int $mode): array
    {
        if ([$position, $mode] === $this->lastReadParams) {
            return $this->lastReadResult;
        }

        if (false === ($row = pg_fetch_array($this->getNative(), $position, $mode))) {
            throw new exceptions\RuntimeException(sprintf("Failed to fetch row %d in result set", $position));
        }
        foreach ($row as $key => &$value) {
            if (PGSQL_ASSOC === $mode) {
                $value = $this->converters[$this->namesHash[$key]]->input($value);
            } else {
                $value = $this->converters[$key]->input($value);
            }
        }
        $this->lastReadParams = [$position, $mode];
        $this->lastReadResult = $row;
        return $row;
    }
}
