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
    /**
     * Type converters, indexed by column number
     * @var TypeConverter[]
     */
    private array $converters = [];

    /** Number of affected rows for DML query */
    private readonly int $affectedRows;

    /** Number of rows in result */
    private int $numRows = 0;

    /** Number of columns in result */
    private int $numFields = 0;

    /**
     * Hash (column name => column number)
     * @var array<string, int>
     */
    private array $namesHash = [];

    /**
     * Table OIDs, indexed by column number
     * @var array<int, int|numeric-string|null>
     */
    private array $tableOIDs = [];

    /** Current iterator position */
    private int $position = 0;

    private int $mode = \PGSQL_ASSOC;

    /**
     * Arguments for last call to read() method
     * @var array{int,int}|null
     */
    private ?array $lastReadParams = null;

    /**
     * Result of last read() call
     */
    private array $lastReadResult = [];

    /**
     * Constructor.
     *
     * @param NativeResult         $native  SQL result object.
     * @param TypeConverterFactory $converterFactory Factory for database type converters (mostly needed for setType())
     * @param array                $types   Types information, used to convert output values
     *                                      (overrides auto-generated types).
     * @throws exceptions\InvalidArgumentException
     * @throws exceptions\RuntimeException
     *
     * @psalm-suppress PossiblyInvalidArgument
     */
    protected function __construct(
        private readonly NativeResult $native,
        private readonly TypeConverterFactory $converterFactory,
        array $types = []
    ) {
        $this->affectedRows = \pg_affected_rows($this->native);

        if (\PGSQL_TUPLES_OK === \pg_result_status($this->native)) {
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
        $this->numRows   = \pg_num_rows($native);
        $this->numFields = \pg_num_fields($native);

        $OIDs = [];
        for ($i = 0; $i < $this->numFields; $i++) {
            $this->namesHash[\pg_field_name($native, $i)] = $i;
            $this->tableOIDs[$i] = \pg_field_table($native, $i, true) ?: null;
            $OIDs[$i] = \pg_field_type_oid($native, $i);
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
     * @throws exceptions\InvalidArgumentException
     * @throws exceptions\RuntimeException
     * @throws exceptions\ServerException
     */
    public static function createFromReturnValue(
        false|NativeResult $returnValue,
        Connection $connection,
        array $types = []
    ): static {
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
    public function setType(int|string $fieldIndex, mixed $type): self
    {
        $this->converters[$this->normalizeFieldIndex($fieldIndex)] =
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
    public function setMode(int $mode = \PGSQL_ASSOC): self
    {
        if (\PGSQL_ASSOC !== $mode && \PGSQL_NUM !== $mode) {
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
     * @throws exceptions\InvalidArgumentException
     * @throws exceptions\OutOfBoundsException
     */
    public function fetchColumn(int|string $fieldIndex): array
    {
        $fieldIndex = $this->normalizeFieldIndex($fieldIndex);

        $result = [];
        $native = $this->getNative();
        for ($i = 0; $i < $this->numRows; $i++) {
            if (false === $field = \pg_fetch_result($native, $i, $fieldIndex)) {
                throw new exceptions\RuntimeException(\sprintf(
                    "Failed to fetch field %d in row %d of result set",
                    $fieldIndex,
                    $i
                ));
            }
            $result[] = $this->converters[$fieldIndex]->input($field);
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
     * @throws exceptions\InvalidArgumentException
     * @throws exceptions\OutOfBoundsException
     */
    public function fetchAll(
        ?int $mode = null,
        int|string|null $keyColumn = null,
        bool $forceArray = false,
        bool $group = false
    ): array {
        if (null === $mode) {
            $mode = $this->mode;
        } elseif (\PGSQL_ASSOC !== $mode && \PGSQL_NUM !== $mode) {
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
            $fieldIndex = $this->normalizeFieldIndex($keyColumn);
            if (\PGSQL_NUM === $mode) {
                $keyColumn = $fieldIndex;
            } elseif (!\is_string($keyColumn) || $keyColumn === (string)$fieldIndex) {
                $keyColumn = \pg_field_name($this->getNative(), $fieldIndex);
            }
        }
        $killArray = (!$forceArray && 2 === $this->numFields);

        $result = [];

        for ($i = 0; $i < $this->numRows; $i++) {
            $row = $this->read($i, $mode);
            if (null === $keyColumn) {
                $result[] = $row;

            } else {
                if (!\is_int($keyColumn)) {
                    $key = $row[$keyColumn];
                    unset($row[$keyColumn]);
                } else {
                    [$key] = \array_splice($row, $keyColumn, 1, []);
                }
                if ($killArray) {
                    $row = \reset($row);
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
        return \array_flip($this->namesHash);
    }

    /**
     * Returns the number of fields in the result
     */
    public function getFieldCount(): int
    {
        return $this->numFields;
    }

    /**
     * Returns the OID for a table that contains the given result field
     *
     * Will return null if the field is e.g. a literal or a calculated value
     */
    public function getTableOID(int|string $fieldIndex): int|string|null
    {
        return $this->tableOIDs[$this->normalizeFieldIndex($fieldIndex)];
    }

    /**
     * Destructor. Frees the result resource.
     */
    public function __destruct()
    {
        // This is only for the mocks, where newInstanceWithoutConstructor() is used
        /** @psalm-suppress RedundantCondition */
        /** @phpstan-ignore isset.initializedProperty */
        if (isset($this->native)) {
            \pg_free_result($this->native);
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
        return $this->position >= 0 && $this->position < $this->numRows;
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
     * Returns an iterator over a single column of the result
     *
     * @param int|string $fieldIndex Either a column name or an index (0-based)
     * @return \Traversable<int, mixed>
     * @since 3.0.0
     */
    public function iterateColumn(int|string $fieldIndex): \Traversable
    {
        $fieldIndex = $this->normalizeFieldIndex($fieldIndex);
        $native     = $this->getNative();

        for ($i = 0; $i < $this->numRows; $i++) {
            if (false === $field = \pg_fetch_result($native, $i, $fieldIndex)) {
                throw new exceptions\RuntimeException(\sprintf(
                    "Failed to fetch field %d in row %d of result set",
                    $fieldIndex,
                    $i
                ));
            }
            yield $this->converters[$fieldIndex]->input($field);
        }
    }

    /**
     * Returns an iterator over result with values representing result rows as enumerated arrays
     *
     * @psalm-suppress MoreSpecificReturnType
     * @return \Traversable<int,list<mixed>>
     * @since 3.0.0
     */
    public function iterateNumeric(): \Traversable
    {
        for ($i = 0; $i < $this->numRows; $i++) {
            yield $this->read($i, \PGSQL_NUM);
        }
    }

    /**
     * Returns an iterator over result with values representing result rows as associative arrays
     *
     * @return \Traversable<int, array<string, mixed>>
     * @since 3.0.0
     */
    public function iterateAssociative(): \Traversable
    {
        for ($i = 0; $i < $this->numRows; $i++) {
            yield $this->read($i, \PGSQL_ASSOC);
        }
    }

    /**
     * Returns an iterator over result with keys corresponding to the values of the given column and values
     * representing either the values of the remaining column or the rest of the columns as associative arrays
     *
     * @param string|null $keyColumn If null, the first column will be used
     * @param bool $forceArray       Applicable when the query returns exactly two columns. If false (default)
     *                               the other column's values will be returned directly, if true they will be
     *                               wrapped in an array keyed with the column name
     *
     * @return \Traversable<mixed, array<string, mixed>|mixed>
     * @since 3.0.0
     */
    public function iterateKeyedAssociative(?string $keyColumn = null, bool $forceArray = false): \Traversable
    {
        if ($this->numFields < 2) {
            throw new exceptions\OutOfBoundsException("At least two columns needed for key-value result iteration");
        }
        if (null === $keyColumn) {
            /** @var string $keyColumn */
            $keyColumn = \array_key_first($this->namesHash);
        } elseif (!isset($this->namesHash[$keyColumn])) {
            throw new exceptions\OutOfBoundsException(
                \sprintf("%s: field name '%s' is not present", __METHOD__, $keyColumn)
            );
        }
        $killArray = (!$forceArray && 2 === $this->numFields);

        for ($i = 0; $i < $this->numRows; $i++) {
            $row = $this->read($i, \PGSQL_ASSOC);
            $key = $row[$keyColumn];
            unset($row[$keyColumn]);

            yield $key => $killArray ? \reset($row) : $row;
        }
    }

    /**
     * Returns an iterator over result with keys corresponding to the values of the column with the given index and
     * values representing either the values of the remaining column or the rest of the columns as enumerated arrays
     *
     * @param bool $forceArray Applicable when the query returns exactly two columns. If false (default)
     *                         the other column's values will be returned directly, if true they will be
     *                         wrapped in an array
     * @return \Traversable<mixed, list<mixed>|mixed>
     * @since 3.0.0
     */
    public function iterateKeyedNumeric(int $keyColumn = 0, bool $forceArray = false): \Traversable
    {
        if ($this->numFields < 2) {
            throw new exceptions\OutOfBoundsException("At least two columns needed for key-value result iteration");
        }
        if ($keyColumn < 0 || $keyColumn >= $this->numFields) {
            throw new exceptions\OutOfBoundsException(\sprintf(
                "%s: field number %d is not within range 0..%d",
                __METHOD__,
                $keyColumn,
                $this->numFields - 1
            ));
        }
        $killArray = (!$forceArray && 2 === $this->numFields);

        for ($i = 0; $i < $this->numRows; $i++) {
            $row   = $this->read($i, \PGSQL_NUM);
            [$key] = \array_splice($row, $keyColumn, 1);

            yield $key => $killArray ? \reset($row) : $row;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists(mixed $offset): bool
    {
        $intOffset = (int)$offset;

        return (string)$intOffset === (string)$offset
            && $intOffset >= 0
            && $intOffset < $this->numRows;
    }

    /**
     * {@inheritdoc}
     * @psalm-return array|null
     * @psalm-ignore-nullable-return
     */
    public function offsetGet(mixed $offset): ?array
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
    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new exceptions\BadMethodCallException(self::class . ' is read-only');
    }

    /**
     * Disallows unsetting the offset
     *
     * @param mixed $offset (not used)
     * @throws exceptions\BadMethodCallException
     */
    public function offsetUnset(mixed $offset): never
    {
        throw new exceptions\BadMethodCallException(self::class . ' is read-only');
    }

    /**
     * Sanity check for field index
     *
     * @return int Numeric index of field in result set
     * @throws exceptions\InvalidArgumentException
     * @throws exceptions\OutOfBoundsException
     */
    private function normalizeFieldIndex(int|string $fieldIndex): int
    {
        if (\is_string($fieldIndex) && isset($this->namesHash[$fieldIndex])) {
            return $this->namesHash[$fieldIndex];

        } elseif (\is_int($fieldIndex) || \ctype_digit($fieldIndex)) {
            if ($fieldIndex >= 0 && $fieldIndex < $this->numFields) {
                return (int)$fieldIndex;
            }
            throw new exceptions\OutOfBoundsException(\sprintf(
                "%s: field number %d is not within range 0..%d",
                __METHOD__,
                $fieldIndex,
                $this->numFields - 1
            ));

        } else {
            throw new exceptions\OutOfBoundsException(
                \sprintf("%s: field name '%s' is not present", __METHOD__, $fieldIndex)
            );
        }
    }

    /**
     * Retrieves the row from result and performs type conversion on it
     *
     * @param int $position row number
     * @param int $mode     fetch mode, either of PGSQL_ASSOC or PGSQL_NUM
     */
    private function read(int $position, int $mode): array
    {
        if ([$position, $mode] === $this->lastReadParams) {
            return $this->lastReadResult;
        }

        if (false === ($row = \pg_fetch_array($this->getNative(), $position, $mode))) {
            throw new exceptions\RuntimeException(\sprintf("Failed to fetch row %d in result set", $position));
        }
        foreach ($row as $key => &$value) {
            if (\PGSQL_ASSOC === $mode) {
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
