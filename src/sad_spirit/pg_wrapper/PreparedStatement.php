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

/**
 * Class representing a prepared statement
 */
class PreparedStatement
{
    /** Whether to fetch parameter types from DB when first preparing the statement */
    private static bool $autoFetchParameterTypes = true;

    /** Used to generate statement names for pg_prepare() */
    protected static int $statementIdx = 0;

    /** Statement name for pg_prepare() / pg_execute() */
    private ?string $queryId = null;

    /**
     * Values for input parameters
     * @var array<int, mixed>
     */
    private array $values = [];

    /**
     * Converters for input parameters
     * @var TypeConverter[]
     */
    private array $converters = [];

    /** Number of parameters in the prepared statement */
    private ?int $numberOfParameters = null;

    /**
     * Sets whether parameter types should be automatically fetched after first preparing a statement
     *
     * @since 2.4.0
     */
    public static function setAutoFetchParameterTypes(bool $autoFetch): void
    {
        self::$autoFetchParameterTypes = $autoFetch;
    }

    /**
     * Returns whether parameter types will be automatically fetched after first preparing a statement
     *
     * @since 2.4.0
     */
    public static function getAutoFetchParameterTypes(): bool
    {
        return self::$autoFetchParameterTypes;
    }

    /**
     * Constructor.
     *
     * @param Connection               $connection  DB connection object.
     * @param string                   $query       SQL query to prepare.
     * @param array<int, mixed>        $paramTypes  Types information used to convert input parameters.
     * @param array<int|string, mixed> $resultTypes Result types to pass to created Result instances.
     *
     * @internal Should only be created by {@see \sad_spirit\pg_wrapper\Connection Connection}
     *
     * @throws exceptions\ServerException
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly string $query,
        array $paramTypes = [],
        private array $resultTypes = []
    ) {
        foreach ($paramTypes as $key => $type) {
            if (!\is_int($key)) {
                throw new exceptions\InvalidArgumentException('$paramTypes array should contain only integer keys');
            } elseif (null !== $type) {
                $this->setParameterType($key + 1, $type);
            }
        }

        $this->prepare();

        if (self::getAutoFetchParameterTypes()) {
            $this->fetchParameterTypes();
        }
    }

    /**
     * Destructor, deallocates the prepared statement
     */
    public function __destruct()
    {
        if (null !== $this->queryId && $this->connection->isConnected() && !$this->connection->needsRollback()) {
            $this->connection->execute('deallocate ' . $this->queryId);
        }
    }

    /**
     * Re-prepares the statement and removes bound values in cloned object
     */
    public function __clone()
    {
        $this->queryId = null;
        $this->values  = [];
        $this->prepare();
    }

    /**
     * Sets result types that will be passed to created Result instances
     *
     * We can theoretically check whether the array keys correspond to correct column _numbers_,
     * but we cannot know the result column _names_ until actually executing the statement.
     * Therefore, don't bother checking array keys, Result will complain if something is not right with them.
     *
     * @return $this
     * @since 2.4.0
     */
    public function setResultTypes(array $resultTypes): self
    {
        $this->resultTypes = $resultTypes;

        return $this;
    }

    /**
     * Actually prepares the statement with pg_prepare()
     *
     * @return $this
     * @throws exceptions\ServerException
     * @throws exceptions\RuntimeException
     */
    public function prepare(): self
    {
        if ($this->queryId) {
            throw new exceptions\RuntimeException('The statement has already been prepared');
        }

        $this->queryId = 'statement' . ++self::$statementIdx;
        if (!@\pg_prepare($this->connection->getNative(), $this->queryId, $this->query)) {
            throw exceptions\ServerException::fromConnection($this->connection);
        }

        return $this;
    }

    /**
     * Manually deallocates the prepared statement
     *
     * This is usually not needed as all the prepared statements are automatically
     * deallocated when database connection is closed. Trying to call
     * {@see \sad_spirit\pg_wrapper\PreparedStatement::execute() execute()} or
     * {@see \sad_spirit\pg_wrapper\PreparedStatement::executeParams() executeParams()}
     * after `deallocate()` will result in an Exception.
     *
     * @return $this
     * @throws exceptions\ServerException
     * @throws exceptions\RuntimeException
     */
    public function deallocate(): self
    {
        if (!$this->queryId) {
            throw new exceptions\RuntimeException('The statement has already been deallocated');
        }

        $this->connection->execute('deallocate ' . $this->queryId);
        $this->queryId = null;

        return $this;
    }

    /**
     * Fetches info about the types assigned to query parameters from the database
     *
     * PHP's `pgsql` extension does not provide a wrapper for `PQdescribePrepared()` function of `libpq`, so we query
     * the `pg_catalog.pg_prepared_statements` view instead.
     *
     * This method will always set parameter count to a correct value, but will not change existing type converters
     * for parameters unless `$overrideExistingTypes` is true
     *
     * @param bool $overrideExistingTypes Whether to override the types that were already set for the parameters
     *
     * @return $this
     * @since 2.4.0
     */
    public function fetchParameterTypes(bool $overrideExistingTypes = false): self
    {
        $preparedInfo = $this->connection->executeParams(
            'select parameter_types::oid[] as types from pg_catalog.pg_prepared_statements where name = $1::text',
            [$this->queryId]
        )
            ->current();

        if (null === $preparedInfo) {
            throw new exceptions\RuntimeException('Failed to fetch info for the prepared statement');
        }

        $this->setNumberOfParameters(\count($preparedInfo['types']));
        foreach ($preparedInfo['types'] as $key => $typeOID) {
            if (!isset($this->converters[$key]) || $overrideExistingTypes) {
                $this->converters[$key] = $this->connection->getTypeConverterFactory()
                    ->getConverterForTypeOID($typeOID);
            }
        }

        return $this;
    }

    /**
     * Sets number of parameters used in the query
     *
     * Parameter symbols should start with `$1` and have no gaps in numbers, otherwise Postgres will throw an error,
     * so setting their number is sufficient.
     *
     * @return $this
     * @since 2.4.0
     */
    public function setNumberOfParameters(int $numberOfParameters): self
    {
        if ($numberOfParameters < 0) {
            throw new exceptions\InvalidArgumentException(\sprintf(
                "%s: number of parameters should be a non-negative integer, %d given",
                __METHOD__,
                $numberOfParameters
            ));
        }

        $this->numberOfParameters = $numberOfParameters;

        return $this;
    }

    /**
     * Sets the type for a parameter of a prepared query
     *
     * @param int   $parameterNumber Parameter number, 1-based
     * @param mixed $type            Type name / converter object
     * @return $this
     * @since 2.4.0
     *
     * @throws exceptions\OutOfBoundsException
     * @throws exceptions\InvalidArgumentException
     */
    public function setParameterType(int $parameterNumber, mixed $type): self
    {
        $this->assertValidParameterNumber($parameterNumber, __METHOD__);

        $this->converters[$parameterNumber - 1] = $this->connection->getTypeConverter($type);

        return $this;
    }

    /**
     * Sets the value for a parameter of a prepared query
     *
     * @param int   $parameterNumber Parameter number, 1-based
     * @param mixed $value           Parameter value
     * @param mixed $type            Type name / converter object to use for converting to DB type
     * @return $this
     *
     * @throws exceptions\OutOfBoundsException
     */
    public function bindValue(int $parameterNumber, mixed $value, mixed $type = null): self
    {
        $this->assertValidParameterNumber($parameterNumber, __METHOD__);

        if (null !== $type) {
            $this->setParameterType($parameterNumber, $type);
        } elseif (!isset($this->converters[$parameterNumber - 1])) {
            throw new exceptions\InvalidArgumentException(
                "Missing bound value type: it should be specified either in \$type parameter or "
                . "beforehand using e.g. setParameterType()"
            );
        }

        $this->values[$parameterNumber - 1] = $value;

        return $this;
    }

    /**
     * Binds a variable to a parameter of a prepared query
     *
     * @param int   $parameterNumber Parameter number, 1-based
     * @param mixed $param           Variable to bind
     * @param mixed $type            Type name / converter object to use for converting to DB type
     * @return $this
     *
     * @throws exceptions\OutOfBoundsException
     */
    public function bindParam(int $parameterNumber, mixed &$param, mixed $type = null): self
    {
        $this->assertValidParameterNumber($parameterNumber, __METHOD__);

        if (null !== $type) {
            $this->setParameterType($parameterNumber, $type);
        } elseif (!isset($this->converters[$parameterNumber - 1])) {
            throw new exceptions\InvalidArgumentException(
                "Missing bound variable type: it should be specified either in \$type parameter or "
                . "beforehand using e.g. setParameterType()"
            );
        }

        $this->values[$parameterNumber - 1] =& $param;

        return $this;
    }

    /**
     * Checks that a given parameter number is valid for this prepared statement
     *
     * @param string $method          Name of the calling method, used in exception message
     *
     * @throws exceptions\OutOfBoundsException
     */
    private function assertValidParameterNumber(int $parameterNumber, string $method): void
    {
        if (0 === $this->numberOfParameters) {
            throw new exceptions\OutOfBoundsException(\sprintf(
                "%s: the prepared statement has no parameters",
                $method
            ));
        } elseif ($parameterNumber < 1) {
            throw new exceptions\OutOfBoundsException(\sprintf(
                "%s: parameter number should be an integer >= 1, %d given",
                $method,
                $parameterNumber
            ));
        } elseif (null !== $this->numberOfParameters && $parameterNumber > $this->numberOfParameters) {
            throw new exceptions\OutOfBoundsException(\sprintf(
                '%s: parameter number should be <= %d, %d given',
                $method,
                $this->numberOfParameters,
                $parameterNumber
            ));
        }
    }

    /**
     * Checks that the array keys correspond to statement placeholders
     *
     * @param array<int, mixed> $params
     * @param string            $prefix Prefix for exception message
     *
     * @throws exceptions\OutOfBoundsException
     */
    private function assertArrayKeysMatchPlaceholders(array $params, string $prefix): void
    {
        if (null !== $this->numberOfParameters) {
            $numberOfParameters = $this->numberOfParameters;
        } elseif (null !== ($lastKey = \array_key_last($params))) {
            $numberOfParameters = $lastKey + 1;
        } else {
            $numberOfParameters = 0;
        }

        if (
            0 === $numberOfParameters && [] === $params
            || $numberOfParameters === \count($params) && $params === \array_values($params)
        ) {
            return;
        }

        $expectedKeys = \range(0, $numberOfParameters - 1);
        $actualKeys   = \array_keys($params);
        $message      = $prefix . ' do not match statement placeholders';

        if ([] !== ($missing = \array_diff($expectedKeys, $actualKeys))) {
            $message .= ', missing values for parameters: $'
                . \implode(', $', \array_map(fn($key): int => $key + 1, $missing));
        }
        if ([] !== ($extra = \array_diff($actualKeys, $expectedKeys))) {
            $message .= ', containing values for nonexistent parameters: $'
                . \implode(', $', \array_map(fn($key): int => $key + 1, $extra));
        }

        throw new exceptions\OutOfBoundsException($message);
    }

    /**
     * Executes a prepared query using previously bound values
     *
     * @return Result Execution result.
     * @throws exceptions\TypeConversionException
     * @throws exceptions\ServerException
     * @throws exceptions\RuntimeException
     */
    public function execute(): Result
    {
        if (!$this->queryId) {
            throw new exceptions\RuntimeException('The statement has already been deallocated');
        }
        $this->connection->assertRollbackNotNeeded();

        \ksort($this->values);
        $this->assertArrayKeysMatchPlaceholders($this->values, 'Bound values');

        $stringParams = [];
        foreach ($this->values as $key => $value) {
            // This shouldn't happen due to logic in bind*() methods, but check just in case
            if (!isset($this->converters[$key])) {
                throw new exceptions\RuntimeException(\sprintf(
                    'Parameter $%d did not have its type specified. Either pass type specifications to constructor '
                    . 'or use setParameterType() method.',
                    $key + 1
                ));
            }
            $stringParams[$key] = $this->converters[$key]->output($value);
        }

        return Result::createFromReturnValue(
            @\pg_execute($this->connection->getNative(), $this->queryId, $stringParams),
            $this->connection,
            $this->resultTypes
        );
    }

    /**
     * Executes the prepared query using (only) the given parameters
     *
     * $params should have integer keys with (0-based) key `N` corresponding to (1-based) statement
     * placeholder `$(N + 1)`. Unlike native `pg_execute()`, array keys will be respected and values mapped by keys
     * rather than in "array order":
     *  - passing `['foo', 'bar']` will use `'foo'` for `$1` and `'bar'` for `$2`, while
     *  - `[1 => 'foo', 0 => 'bar']` will use `'bar'` for `$1` and `'foo'` for `$2`.
     *
     * This method will throw an exception if some parameter values were bound previously.
     *
     * @param array<int, mixed> $params
     * @since 2.4.0
     */
    public function executeParams(array $params): Result
    {
        if (!$this->queryId) {
            throw new exceptions\RuntimeException('The statement has already been deallocated');
        }
        if ([] !== $this->values) {
            throw new exceptions\RuntimeException(
                "Some parameters already have bound values, use execute() method "
                . "to execute the statement with these."
            );
        }
        $this->connection->assertRollbackNotNeeded();

        \ksort($params);
        $this->assertArrayKeysMatchPlaceholders($params, 'Keys of $params');

        $stringParams = [];
        foreach ($params as $key => $value) {
            if (!isset($this->converters[$key])) {
                throw new exceptions\RuntimeException(\sprintf(
                    'Parameter $%d did not have its type specified. Either pass type specifications to constructor '
                    . 'or use setParameterType() method.',
                    $key + 1
                ));
            }
            $stringParams[$key] = $this->converters[$key]->output($value);
        }

        return Result::createFromReturnValue(
            @\pg_execute($this->connection->getNative(), $this->queryId, $stringParams),
            $this->connection,
            $this->resultTypes
        );
    }
}
