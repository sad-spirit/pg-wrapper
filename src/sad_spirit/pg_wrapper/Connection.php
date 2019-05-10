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

use Psr\Cache\CacheItemPoolInterface;

/**
 * Class representing a connection to the database
 */
class Connection
{
    /**
     * Connection resource
     * @var resource
     */
    private $resource;

    /**
     * Connection string (as used for pg_connect())
     * @var string
     */
    private $connectionString;

    /**
     * Type conversion factory for this connection
     * @var TypeConverterFactory
     */
    private $converterFactory;

    /**
     * Cache for database metadata
     * @var CacheItemPoolInterface
     */
    private $cacheItemPool;

    /**
     * Constructor.
     *
     * @param string $connectionString Connection string.
     * @param bool   $lazy             Whether to postpone connecting until needed
     * @throws exceptions\ConnectionException
     */
    public function __construct($connectionString, bool $lazy = true)
    {
        $this->connectionString = $connectionString;
        if (!$lazy) {
            $this->connect();
        }
    }

    /**
     * Destructor. Closes connection to database, if needed.
     */
    public function __destruct()
    {
        $this->converterFactory = null;
        $this->disconnect();
    }

    /**
     * Forces opening a new database connection for cloned object
     */
    public function __clone()
    {
        $this->resource = null;
    }

    /**
     * Explicitly connects to the database
     *
     * @return $this
     * @throws exceptions\ConnectionException
     */
    public function connect(): self
    {
        if ($this->resource) {
            return $this;
        }

        $connectionWarnings = [];
        set_error_handler(function ($errno, $errstr) use (&$connectionWarnings) {
            $connectionWarnings[] = $errstr;
            return true;
        }, E_WARNING);

        $this->resource = pg_connect($this->connectionString, PGSQL_CONNECT_FORCE_NEW);

        restore_error_handler();
        if (false === $this->resource) {
            throw new exceptions\ConnectionException(
                __METHOD__ . ': ' . implode("\n", $connectionWarnings)
            );
        }
        $serverVersion = pg_parameter_status($this->resource, 'server_version');
        if (version_compare($serverVersion, '9.2.0', '<')) {
            $this->disconnect();
            throw new exceptions\ConnectionException(
                __METHOD__ . ': PostgreSQL versions earlier than 9.2 are no longer supported, '
                . 'connected server reports version ' . $serverVersion
            );
        }
        pg_set_error_verbosity($this->resource, PGSQL_ERRORS_VERBOSE);

        return $this;
    }

    /**
     * Disconnects from the database
     */
    public function disconnect(): self
    {
        if ($this->isConnected()) {
            pg_close($this->resource);
            $this->resource = null;
        }

        return $this;
    }

    /**
     * Checks whether a connection was made
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        return is_resource($this->resource);
    }

    /**
     * Returns database connection resource
     *
     * @return resource
     */
    public function getResource()
    {
        if (!$this->isConnected()) {
            $this->connect();
        }
        return $this->resource;
    }

    /**
     * Returns a unique identifier for connection
     *
     * @return string
     */
    public function getConnectionId(): string
    {
        return 'pg' . sprintf('%x', crc32(get_class($this) . ' ' . $this->connectionString));
    }

    /**
     * Quotes a value for inclusion in query, taking connection encoding into account
     *
     * @param mixed $value
     * @param mixed $type
     * @return string
     * @throws exceptions\TypeConversionException
     */
    public function quote($value, $type = null): string
    {
        if (null === $value) {
            return 'NULL';
        }

        $resource = $this->getResource(); // forces connecting if not connected yet
        if (null !== $type) {
            return "'" . pg_escape_string($resource, $this->getTypeConverter($type)->output($value)) . "'";
        } else {
            return "'" . pg_escape_string(
                $resource,
                $this->getTypeConverterFactory()
                    ->getConverterForPHPValue($value)
                    ->output($value)
            ) . "'";
        }
    }

    /**
     * Prepares a given query for execution.
     *
     * @param string $query      SQL query to prepare.
     * @param array  $paramTypes Types information used to convert input parameters
     *
     * @return PreparedStatement Prepared statement.
     * @throws exceptions\ServerException
     */
    public function prepare(string $query, array $paramTypes = []): PreparedStatement
    {
        return new PreparedStatement($this, $query, $paramTypes);
    }

    /**
     * Executes a given query
     *
     * For queries that return rows this method returns a ResultSet object, for
     * data modification queries it returns the number of affected rows
     *
     * @param string $sql         SQL query to execute
     * @param array  $resultTypes Type converters to pass to ResultSet
     *
     * @return ResultSet|int|bool Execution result.
     * @throws exceptions\ServerException
     */
    public function execute(string $sql, array $resultTypes = [])
    {
        $result = @pg_query($this->getResource(), $sql);

        if (false === $result) {
            throw exceptions\ServerException::fromConnection($this->getResource());
        }

        switch (pg_result_status($result)) {
            case PGSQL_COMMAND_OK:
                $rows = pg_affected_rows($result);
                pg_free_result($result);
                return $rows;

            case PGSQL_COPY_IN:
            case PGSQL_COPY_OUT:
                pg_free_result($result);
                return true;

            case PGSQL_TUPLES_OK:
            default:
                return new ResultSet($result, $this->getTypeConverterFactory(), $resultTypes);
        }
    }

    /**
     * Executes a given query with the ability to pass parameters separately
     *
     * @param string $sql         Query
     * @param array  $params      Parameters
     * @param array  $paramTypes  Types information used to convert input parameters
     * @param array  $resultTypes Result types to pass to ResultSet
     *
     * @return bool|ResultSet|int
     * @throws exceptions\ServerException
     */
    public function executeParams(string $sql, array $params, array $paramTypes = [], array $resultTypes = [])
    {
        if (!$this->isConnected()) {
            $this->connect();
        }
        $stringParams = [];
        foreach ($params as $key => $value) {
            if (isset($paramTypes[$key])) {
                $stringParams[$key] = $this->getTypeConverter($paramTypes[$key])->output($value);
            } else {
                $stringParams[$key] = $this->getTypeConverterFactory()
                    ->getConverterForPHPValue($value)
                    ->output($value);
            }
        }

        $result = @pg_query_params($this->getResource(), $sql, $stringParams);
        if (false === $result) {
            throw exceptions\ServerException::fromConnection($this->getResource());
        }

        switch (pg_result_status($result)) {
            case PGSQL_COMMAND_OK:
                $rows = pg_affected_rows($result);
                pg_free_result($result);
                return $rows;

            case PGSQL_COPY_IN:
            case PGSQL_COPY_OUT:
                pg_free_result($result);
                return true;

            case PGSQL_TUPLES_OK:
            default:
                return new ResultSet($result, $this->getTypeConverterFactory(), $resultTypes);
        }
    }

    /**
     * Get the factory object for converters to and from PostreSQL representation
     *
     * @return TypeConverterFactory
     */
    public function getTypeConverterFactory(): TypeConverterFactory
    {
        if (!$this->converterFactory) {
            $this->setTypeConverterFactory(new converters\DefaultTypeConverterFactory());
        }
        return $this->converterFactory;
    }

    /**
     * Sets the factory object for converters to and from PostgreSQL types
     *
     * @param TypeConverterFactory $factory
     * @return $this
     */
    public function setTypeConverterFactory(TypeConverterFactory $factory): self
    {
        $this->converterFactory = $factory;
        $factory->setConnection($this);

        return $this;
    }

    /**
     * Returns type converter for the given database type
     *
     * @param mixed $type
     * @return TypeConverter
     */
    public function getTypeConverter($type): TypeConverter
    {
        return $this->getTypeConverterFactory()->getConverterForTypeSpecification($type);
    }

    /**
     * Returns the DB metadata cache
     *
     * @return CacheItemPoolInterface|null
     */
    public function getMetadataCache(): ?CacheItemPoolInterface
    {
        return $this->cacheItemPool;
    }

    /**
     * Sets the DB metadata cache
     *
     * @param CacheItemPoolInterface $cache Cache instance
     * @return $this
     */
    public function setMetadataCache(CacheItemPoolInterface $cache): self
    {
        $this->cacheItemPool = $cache;

        return $this;
    }

    /**
     * Starts a transaction or sets a savepoint
     *
     * @param string|null $savepoint savepoint name
     * @return $this
     * @throws exceptions\RuntimeException if trying to create a savepoint outside the transaction block
     */
    public function beginTransaction(?string $savepoint = null): self
    {
        if (null === $savepoint) {
            if (!$this->inTransaction()) {
                $this->execute('BEGIN');
            }

        } elseif (!$this->inTransaction()) {
            throw new exceptions\RuntimeException(
                __METHOD__ . ': Savepoints can only be used in transaction blocks'
            );

        } else {
            $this->execute('SAVEPOINT ' . $savepoint);
        }

        return $this;
    }

    /**
     * Commits a transaction or releases a savepoint
     *
     * @param string|null $savepoint savepoint name
     * @return $this
     * @throws exceptions\RuntimeException if trying to release a savepoint outside the transaction block
     */
    public function commit(?string $savepoint = null): self
    {
        if (null === $savepoint) {
            if ($this->inTransaction()) {
                $this->execute('COMMIT');
            }

        } elseif (!$this->inTransaction()) {
            throw new exceptions\RuntimeException(
                __METHOD__ . ': Savepoints can only be used in transaction blocks'
            );

        } else {
            $this->execute('RELEASE SAVEPOINT ' . $savepoint);
        }

        return $this;
    }

    /**
     * Rolls back changes done during a transaction or since a specific savepoint
     *
     * @param string|null $savepoint savepoint name
     * @return $this
     * @throws exceptions\RuntimeException if trying to roll back to a savepoint outside the transaction block
     */
    public function rollback(?string $savepoint = null): self
    {
        if (null === $savepoint) {
            if ($this->inTransaction()) {
                $this->execute('ROLLBACK');
            }

        } elseif (!$this->inTransaction()) {
            throw new exceptions\RuntimeException(
                __METHOD__ . 'Savepoints can only be used in transaction blocks'
            );

        } else {
            $this->execute('ROLLBACK TO SAVEPOINT ' . $savepoint);
        }

        return $this;
    }

    /**
     * Checks whether a transaction is currently open.
     *
     * @return  bool
     */
    public function inTransaction(): bool
    {
        $status = pg_transaction_status($this->getResource());

        return PGSQL_TRANSACTION_INTRANS === $status || PGSQL_TRANSACTION_INERROR === $status;
    }
}
