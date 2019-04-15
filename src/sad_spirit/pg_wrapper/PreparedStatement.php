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
    static protected $statementIdx = 0;

    /**
     * Connection object
     * @var Connection
     */
    private $_connection;

    /**
     * SQL query text
     * @var string
     */
    private $_query;

    /**
     * Statement name for pg_prepare() / pg_execute()
     * @var string
     */
    private $_queryId;

    /**
     * Values for input parameters
     * @var array
     */
    private $_values = [];

    /**
     * Converters for input parameters
     * @var TypeConverter[]
     */
    private $_converters = [];

    /**
     * Constructor.
     *
     * @param Connection $connection Reference to the connection object.
     * @param string     $query      SQL query to prepare.
     * @param array      $paramTypes Types information used to convert input parameters.
     *
     * @throws exceptions\InvalidQueryException
     */
    public function __construct(Connection $connection, string $query, array $paramTypes = [])
    {
        $this->_connection = $connection;
        $this->_query      = $query;

        foreach ($paramTypes as $key => $type) {
            if (null !== $type) {
                $this->_converters[$key] = $this->_connection->getTypeConverter($type);
            }
        }

        $this->prepare();
    }

    /**
     * Re-prepares the statement and removes bound values in cloned object
     */
    public function __clone()
    {
        $this->_queryId = null;
        $this->_values  = [];
        $this->prepare();
    }

    /**
     * Actually prepares the statement with pg_prepare()
     *
     * @return $this
     * @throws exceptions\InvalidQueryException
     * @throws exceptions\RuntimeException
     */
    public function prepare(): self
    {
        if ($this->_queryId) {
            throw new exceptions\RuntimeException('The statement has already been prepared');
        }

        $this->_queryId = 'statement' . ++self::$statementIdx;
        if (!@pg_prepare($this->_connection->getResource(), $this->_queryId, $this->_query)) {
            throw new exceptions\InvalidQueryException(pg_last_error($this->_connection->getResource()));
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
     * @throws exceptions\InvalidQueryException
     * @throws exceptions\RuntimeException
     */
    public function deallocate(): self
    {
        if (!$this->_queryId) {
            throw new exceptions\RuntimeException('The statement has already been deallocated');
        }

        $this->_connection->execute('deallocate ' . $this->_queryId);
        $this->_queryId = null;

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
     * @throws exceptions\InvalidArgumentException
     */
    function bindValue(int $paramNum, $value, $type = null): self
    {
        if (!is_int($paramNum) || $paramNum < 1) {
            throw new exceptions\InvalidArgumentException(sprintf(
                '%s: parameter number should be an integer >= 1, %s given',
                __METHOD__, is_int($paramNum) ? $paramNum : gettype($paramNum)
            ));
        }
        $this->_values[$paramNum - 1] = $value;
        if (null !== $type) {
            $this->_converters[$paramNum - 1] = $this->_connection->getTypeConverter($type);
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
     * @throws exceptions\InvalidArgumentException
     */
    function bindParam(int $paramNum, &$param, $type = null): self
    {
        if (!is_int($paramNum) || $paramNum < 1) {
            throw new exceptions\InvalidArgumentException(sprintf(
                '%s: parameter number should be an integer >= 1, %s given',
                __METHOD__, is_int($paramNum) ? $paramNum : gettype($paramNum)
            ));
        }
        $this->_values[$paramNum - 1] =& $param;
        if (null !== $type) {
            $this->_converters[$paramNum - 1] = $this->_connection->getTypeConverter($type);
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
     * @return ResultSet|int|bool Execution result.
     * @throws exceptions\TypeConversionException
     * @throws exceptions\InvalidQueryException
     * @throws exceptions\RuntimeException
     */
    public function execute(array $params = [], array $resultTypes = [])
    {
        if (!$this->_queryId) {
            throw new exceptions\RuntimeException('The statement has already been deallocated');
        }

        if (!empty($params)) {
            $this->_values = [];
            foreach (array_values($params) as $i => $value) {
                $this->bindValue($i + 1, $value);
            }
        }

        $stringParams = [];
        foreach ($this->_values as $key => $value) {
            if (isset($this->_converters[$key])) {
                $stringParams[$key] = $this->_converters[$key]->output($value);
            } else {
                $stringParams[$key] = $this->_connection->guessOutputFormat($value);
            }
        }

        $result = @pg_execute($this->_connection->getResource(), $this->_queryId, $stringParams);
        if (!$result) {
            throw new exceptions\InvalidQueryException(pg_last_error($this->_connection->getResource()));
        }

        switch (pg_result_status($result)) {
        case PGSQL_COPY_IN:
        case PGSQL_COPY_OUT:
            pg_free_result($result);
            return true;
        case PGSQL_COMMAND_OK:
            $count = pg_affected_rows($result);
            pg_free_result($result);
            return $count;
        case PGSQL_TUPLES_OK:
        default:
            return new ResultSet($result, $this->_connection->getTypeConverterFactory(), $resultTypes);
        }
    }
}
