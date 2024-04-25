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
     * Constructor.
     *
     * @param Connection        $connection Reference to the connection object.
     * @param string            $query      SQL query to prepare.
     * @param array<int, mixed> $paramTypes Types information used to convert input parameters.
     *
     * @throws exceptions\ServerException
     */
    public function __construct(Connection $connection, string $query, array $paramTypes = [])
    {
        $this->connection = $connection;
        $this->query      = $query;

        foreach ($paramTypes as $key => $type) {
            if (null !== $type) {
                $this->converters[$key] = $this->connection->getTypeConverter($type);
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
        if ($paramNum < 1) {
            throw new exceptions\OutOfBoundsException(sprintf(
                '%s: parameter number should be an integer >= 1, %d given',
                __METHOD__,
                $paramNum
            ));
        }
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
        if ($paramNum < 1) {
            throw new exceptions\OutOfBoundsException(sprintf(
                '%s: parameter number should be an integer >= 1, %d given',
                __METHOD__,
                $paramNum
            ));
        }
        $this->values[$paramNum - 1] =& $param;
        if (null !== $type) {
            $this->converters[$paramNum - 1] = $this->connection->getTypeConverter($type);
        }

        return $this;
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
        $this->connection->checkRollbackNotNeeded();

        if (!empty($params)) {
            $this->values = [];
            foreach (array_values($params) as $i => $value) {
                $this->bindValue($i + 1, $value);
            }
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
}
