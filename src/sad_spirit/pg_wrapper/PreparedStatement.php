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
 * @copyright 2014-2023 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

declare(strict_types=1);

namespace sad_spirit\pg_wrapper;

/**
 * Class representing a prepared statement
 */
class PreparedStatement
{
    /**
     * Used to generate statement names for pg_prepare()
     * @var int
     */
    protected static $statementIdx = 0;

    /**
     * Connection object
     * @var Connection
     */
    private $connection;

    /**
     * SQL query text
     * @var string
     */
    private $query;

    /**
     * Statement name for pg_prepare() / pg_execute()
     * @var string|null
     */
    private $queryId;

    /**
     * Values for input parameters
     * @var array<int, mixed>
     */
    private $values = [];

    /**
     * Converters for input parameters
     * @var TypeConverter[]
     */
    private $converters = [];

    /**
     * Types information for output values, passed on to ResultSet
     * @var array
     */
    private $resultTypes;

    /**
     * Constructor.
     *
     * @param Connection               $connection  DB connection object.
     * @param string                   $query       SQL query to prepare.
     * @param array<int, mixed>        $paramTypes  Types information used to convert input parameters.
     * @param array<int|string, mixed> $resultTypes Result types to pass to created ResultSet instances.
     *
     * @throws exceptions\ServerException
     */
    public function __construct(
        Connection $connection,
        string $query,
        array $paramTypes = [],
        array $resultTypes = []
    ) {
        $this->connection  = $connection;
        $this->query       = $query;
        $this->resultTypes = $resultTypes;

        foreach ($paramTypes as $key => $type) {
            if (!\is_int($key)) {
                throw new exceptions\InvalidArgumentException('$paramTypes array should contain only integer keys');
            } elseif (null !== $type) {
                $this->setParameterType($key + 1, $type);
            }
        }

        $this->prepare();
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
     * Sets result types that will be passed to created ResultSet instances
     *
     * We can theoretically check whether the array keys correspond to correct column numbers,
     * but we cannot know the result column names until actually executing the statement.
     * Therefore, don't bother checking array keys, ResultSet will complain if something is not right with them.
     *
     * @param array $resultTypes
     * @return $this
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
        if (!@pg_prepare($this->connection->getNative(), $this->queryId, $this->query)) {
            throw exceptions\ServerException::fromConnection($this->connection);
        }

        return $this;
    }

    /**
     * Manually deallocates the prepared statement
     *
     * This is usually not needed as all the prepared statements are automatically
     * deallocated when database connection is closed. Trying to call execute()
     * after deallocate() will result in an Exception.
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
     * Sets the type for a parameter of a prepared query
     *
     * @param int   $parameterNumber Parameter number, 1-based
     * @param mixed $type            Type name / converter object
     * @return $this
     *
     * @throws exceptions\OutOfBoundsException
     * @throws exceptions\InvalidArgumentException
     */
    public function setParameterType(int $parameterNumber, $type): self
    {
        $this->assertValidParameterNumber($parameterNumber, __METHOD__);

        $this->converters[$parameterNumber - 1] = $this->connection->getTypeConverter($type);

        return $this;
    }

    /**
     * Sets the value for a parameter of a prepared query
     *
     * @param int   $paramNum Parameter number, 1-based
     * @param mixed $value    Parameter value
     * @param mixed $type     Type name / converter object to use for converting to DB type
     * @return $this
     *
     * @throws exceptions\OutOfBoundsException
     */
    public function bindValue(int $paramNum, $value, $type = null): self
    {
        $this->assertValidParameterNumber($paramNum, __METHOD__);

        $this->values[$paramNum - 1] = $value;
        if (null !== $type) {
            $this->converters[$paramNum - 1] = $this->connection->getTypeConverter($type);
        }

        return $this;
    }

    /**
     * Binds a variable to a parameter of a prepared query
     *
     * @param int   $paramNum Parameter number, 1-based
     * @param mixed $param    Variable to bind
     * @param mixed $type     Type name / converter object to use for converting to DB type
     * @return $this
     *
     * @throws exceptions\OutOfBoundsException
     */
    public function bindParam(int $paramNum, &$param, $type = null): self
    {
        $this->assertValidParameterNumber($paramNum, __METHOD__);

        $this->values[$paramNum - 1] =& $param;
        if (null !== $type) {
            $this->converters[$paramNum - 1] = $this->connection->getTypeConverter($type);
        }

        return $this;
    }

    /**
     * Checks that a given parameter number is valid for this prepared statement
     *
     * @param int    $parameterNumber
     * @param string $method          Name of the calling method, used in exception message
     * @return void
     *
     * @throws exceptions\OutOfBoundsException
     */
    private function assertValidParameterNumber(int $parameterNumber, string $method): void
    {
        if ($parameterNumber < 1) {
            throw new exceptions\OutOfBoundsException(\sprintf(
                "%s: parameter number should be an integer >= 1, %d given",
                $method,
                $parameterNumber
            ));
        }
    }

    /**
     * Executes a prepared query
     *
     * @param array $params      Input parameters for query, will override those bound by
     *                           bindValue() and bindParam() methods when provided.
     * @param array $resultTypes Types information for result fields, passed to ResultSet
     *
     * @return ResultSet Execution result.
     * @throws exceptions\TypeConversionException
     * @throws exceptions\ServerException
     * @throws exceptions\RuntimeException
     */
    public function execute(array $params = [], array $resultTypes = []): ResultSet
    {
        if (!$this->queryId) {
            throw new exceptions\RuntimeException('The statement has already been deallocated');
        }
        $this->connection->assertRollbackNotNeeded();

        if (!empty($params)) {
            @\trigger_error(
                'Passing $params to PreparedStatement::execute() is deprecated since release 2.4.0. '
                . 'Either bind the parameters with bindParam() / bindValue() beforehand or use executeParams().',
                \E_USER_DEPRECATED
            );

            $this->values = [];
            foreach (array_values($params) as $i => $value) {
                $this->bindValue($i + 1, $value);
            }
        }
        if ([] === $resultTypes) {
            $resultTypes = $this->resultTypes;
        } else {
            @\trigger_error(
                'Passing $resultTypes to PreparedStatement::execute() is deprecated since release 2.4.0. '
                . 'Set these either via constructor or setResultTypes() method.',
                \E_USER_DEPRECATED
            );
        }

        $stringParams = [];
        foreach ($this->values as $key => $value) {
            if (isset($this->converters[$key])) {
                $stringParams[$key] = $this->converters[$key]->output($value);
            } else {
                $stringParams[$key] = $this->connection->getTypeConverterFactory()
                    ->getConverterForPHPValue($value)
                    ->output($value);
            }
        }

        return ResultSet::createFromReturnValue(
            @pg_execute($this->connection->getNative(), $this->queryId, $stringParams),
            $this->connection,
            $resultTypes
        );
    }

    /**
     * Executes the prepared query using (only) the given parameters
     *
     * This will throw an exception if some parameter values were bound previously
     *
     * @param array $params
     * @return ResultSet
     */
    public function executeParams(array $params): ResultSet
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

        $stringParams = [];
        foreach (\array_values($params) as $key => $value) {
            if (!isset($this->converters[$key])) {
                throw new exceptions\RuntimeException(\sprintf(
                    'Parameter $%d did not have its type specified. Either pass type specifications to constructor '
                    . 'or use setParameterType() method.',
                    $key + 1
                ));
            }
            $stringParams[$key] = $this->converters[$key]->output($value);
        }

        return ResultSet::createFromReturnValue(
            @\pg_execute($this->connection->getNative(), $this->queryId, $stringParams),
            $this->connection,
            $this->resultTypes
        );
    }
}
