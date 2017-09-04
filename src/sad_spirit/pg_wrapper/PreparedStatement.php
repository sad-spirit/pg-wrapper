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
     * Statement name for pg_prepare() / pg_execute()
     * @var string
     */
    private $_queryId;

    /**
     * Values for input parameters
     * @var array
     */
    private $_values = array();

    /**
     * Converters for input parameters
     * @var TypeConverter[]
     */
    private $_converters = array();

    /**
     * Constructor.
     *
     * @param Connection $connection Reference to the connection object.
     * @param string     $query      SQL query to prepare.
     * @param array      $types      Types information, used to convert input params.
     * @throws exceptions\InvalidQueryException
     */
    public function __construct(Connection $connection, $query, array $types = array())
    {
        $this->_connection = $connection;
        $this->_queryId    = 'statement' . ++self::$statementIdx;
        if (!@pg_prepare($this->_connection->getResource(), $this->_queryId, $query)) {
            throw new exceptions\InvalidQueryException(pg_last_error($this->_connection->getResource()));
        }

        foreach ($types as $key => $type) {
            if ($type instanceof TypeConverter) {
                $this->_converters[$key] = $type;
            } elseif (null !== $type) {
                $this->_converters[$key] = $this->_connection->getTypeConverter($type);
            }
        }
    }

    /**
     * Sets the value for a parameter of a prepared query
     *
     * @param int   $paramNum Parameter number, 1-based
     * @param mixed $value    Parameter value
     * @param mixed $type     Type name / converter object to use for converting to DB type
     * @throws exceptions\InvalidArgumentException
     */
    function bindValue($paramNum, $value, $type = null)
    {
        if (!is_int($paramNum) || $paramNum < 1) {
            throw new exceptions\InvalidArgumentException(sprintf(
                '%s: parameter number should be an integer >= 1, %s given',
                __METHOD__, is_int($paramNum) ? $paramNum : gettype($paramNum)
            ));
        }
        $this->_values[$paramNum - 1] = $value;
        if ($type instanceof TypeConverter) {
            $this->_converters[$paramNum - 1] = $type;
        } elseif (null !== $type) {
            $this->_converters[$paramNum - 1] = $this->_connection->getTypeConverter($type);
        }
    }

    /**
     * Binds a variable to a parameter of a prepared query
     *
     * @param int   $paramNum Parameter number, 1-based
     * @param mixed $param    Variable to bind
     * @param mixed $type     Type name / converter object to use for converting to DB type
     * @throws exceptions\InvalidArgumentException
     */
    function bindParam($paramNum, &$param, $type = null)
    {
        if (!is_int($paramNum) || $paramNum < 1) {
            throw new exceptions\InvalidArgumentException(sprintf(
                '%s: parameter number should be an integer >= 1, %s given',
                __METHOD__, is_int($paramNum) ? $paramNum : gettype($paramNum)
            ));
        }
        $this->_values[$paramNum - 1] =& $param;
        if ($type instanceof TypeConverter) {
            $this->_converters[$paramNum - 1] = $type;
        } elseif (null !== $type) {
            $this->_converters[$paramNum - 1] = $this->_connection->getTypeConverter($type);
        }
    }


    /**
     * Executes a prepared query
     *
     * @param array $params Input params for query.
     * @param array $resultTypes Types information, used to convert output values (overrides auto-generated types).
     * @return ResultSet|int|bool Execution result.
     * @throws exceptions\TypeConversionException
     * @throws exceptions\InvalidQueryException
     */
    public function execute(array $params = array(), array $resultTypes = array())
    {
        if (!empty($params)) {
            $this->_values = array();
            foreach (array_values($params) as $i => $value) {
                $this->bindValue($i + 1, $value);
            }
        }

        $params = array();
        foreach ($this->_values as $key => $value) {
            if (isset($this->_converters[$key])) {
                $params[$key] = $this->_converters[$key]->output($value);
            } else {
                $params[$key] = $this->_connection->guessOutputFormat($value);
            }
        }

        $result = @pg_execute($this->_connection->getResource(), $this->_queryId, $params);
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
