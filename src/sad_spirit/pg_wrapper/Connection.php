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
     * Marks whether the connection is in a transaction managed by {@link atomic()}
     * @var bool
     */
    private $inAtomic = false;

    /**
     * Marks whether transaction should be rolled back to the next available savepoint due to error in inner block
     * @var bool
     */
    private $needsRollback = false;

    /**
     * Counter used to generate unique savepoint names
     * @var int
     */
    private $savepointIndex = 0;

    /**
     * Names of savepoints created by {@link atomic()}
     * @var array
     */
    private $savepointNames = [];

    /**
     * Callbacks to run after successful commit of transaction
     *
     * Each entry is an array with two elements
     *  - Names of savepoints active when callback was registered
     *  - Actual callback
     *
     * @var array
     */
    private $onCommitCallbacks = [];

    /**
     * Callbacks to run after rollback of transaction
     *
     * Structure is similar to $onCommitCallbacks
     *
     * @var array
     */
    private $onRollbackCallbacks = [];

    /**
     * Whether a shutdown function to run outstanding onRollback() callbacks was registered
     * @var bool
     */
    private $shutdownRegistered = false;

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
        $this->resource           = null;
        $this->shutdownRegistered = false;
        $this->resetTransactionState();
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
        if (version_compare($serverVersion, '9.3', '<')) {
            $this->disconnect();
            throw new exceptions\ConnectionException(
                __METHOD__ . ': PostgreSQL versions earlier than 9.3 are no longer supported, '
                . 'connected server reports version ' . $serverVersion
            );
        }
        pg_set_error_verbosity($this->resource, PGSQL_ERRORS_VERBOSE);

        $this->resetTransactionState();

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
            return pg_escape_literal($resource, $this->getTypeConverter($type)->output($value));
        } else {
            return pg_escape_literal(
                $resource,
                $this->getTypeConverterFactory()
                    ->getConverterForPHPValue($value)
                    ->output($value)
            );
        }
    }

    /**
     * Quotes an identifier (e.g. table name) for inclusion in a query
     *
     * @param string $identifier
     * @return string
     */
    public function quoteIdentifier(string $identifier): string
    {
        return pg_escape_identifier($this->getResource(), $identifier);
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
        return ResultSet::createFromResultResource(
            @pg_query($this->getResource(), $sql),
            $this,
            $resultTypes
        );
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
        $resource     = $this->getResource();
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

        return ResultSet::createFromResultResource(
            @pg_query_params($resource, $sql, $stringParams),
            $this,
            $resultTypes
        );
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
     * Starts a transaction
     *
     * @return $this
     * @throws exceptions\BadMethodCallException If called within atomic() block
     */
    public function beginTransaction(): self
    {
        if ($this->inAtomic) {
            throw new exceptions\BadMethodCallException(
                "Methods for manual transaction handling should not be called within atomic() closures"
            );
        }

        $this->execute('BEGIN');

        return $this;
    }

    /**
     * Commits a transaction
     *
     * @return $this
     * @throws exceptions\BadMethodCallException If called within atomic() block
     */
    public function commit(): self
    {
        if ($this->inAtomic) {
            throw new exceptions\BadMethodCallException(
                "Methods for manual transaction handling should not be called within atomic() closures"
            );
        }

        $this->execute('COMMIT');

        $this->onRollbackCallbacks = [];
        $this->runAndClearOnCommitCallbacks();

        return $this;
    }

    /**
     * Rolls back a transaction
     *
     * @return $this
     * @throws exceptions\BadMethodCallException If called within atomic() block
     */
    public function rollback(): self
    {
        if ($this->inAtomic) {
            throw new exceptions\BadMethodCallException(
                "Methods for manual transaction handling should not be called within atomic() closures"
            );
        }

        $this->execute('ROLLBACK');

        $this->onCommitCallbacks = [];
        $this->runAndClearOnRollbackCallbacks();

        return $this;
    }

    /**
     * Creates a new savepoint with the given name
     *
     * @param string $savepoint
     * @return $this
     * @throws exceptions\RuntimeException if trying to create a savepoint outside the transaction block
     */
    public function createSavepoint(string $savepoint): self
    {
        if (!$this->inTransaction()) {
            throw new exceptions\RuntimeException(
                __METHOD__ . ': Savepoints can only be used in transaction blocks'
            );
        }

        $this->execute('SAVEPOINT ' . $savepoint);

        return $this;
    }

    /**
     * Releases the given savepoint
     *
     * @param string $savepoint
     * @return $this
     * @throws exceptions\RuntimeException if trying to create a savepoint outside the transaction block
     */
    public function releaseSavepoint(string $savepoint): self
    {
        if (!$this->inTransaction()) {
            throw new exceptions\RuntimeException(
                __METHOD__ . ': Savepoints can only be used in transaction blocks'
            );
        }

        $this->execute('RELEASE SAVEPOINT ' . $savepoint);

        return $this;
    }

    /**
     * Rolls back to the given savepoint
     *
     * @param string $savepoint
     * @return $this
     * @throws exceptions\RuntimeException if trying to create a savepoint outside the transaction block
     */
    public function rollbackToSavepoint(string $savepoint): self
    {
        if (!$this->inTransaction()) {
            throw new exceptions\RuntimeException(
                __METHOD__ . ': Savepoints can only be used in transaction blocks'
            );
        }

        $this->execute('ROLLBACK TO SAVEPOINT ' . $savepoint);

        $this->onCommitCallbacks = array_filter($this->onCommitCallbacks, function ($value) use ($savepoint) {
            return !in_array($savepoint, $value[0]);
        });
        $this->runAndClearOnRollbackCallbacks($savepoint);

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

    /**
     * Runs a given function atomically
     *
     * Before running $callback atomic() ensures the transaction is started and creates a savepoint if asked. Since
     * savepoints add a bit of overhead, their creation is disabled by default.
     *
     * If $callback executes normally then transaction is committed or savepoint is released. In case of exception
     * the transaction is rolled back (to savepoint if one was created) and exception is re-thrown.
     *
     * $callback receives this Connection instance as an argument.
     *
     * It is possible to use {@link onCommit()} and {@link onRollback()} methods inside $callback to register
     * functions that will run after a commit or a rollback of the transaction, respectively. Calling
     * {@link beginTransaction()}, {@link commit()} or {@link rollback()} will fail with an exception to
     * ensure atomicity.
     *
     * @param callable $callback  The function to execute atomically
     * @param bool     $savepoint Whether to create a savepoint if the transaction is already in progress
     *
     * @return mixed The value returned by $callback
     *
     * @throws \Throwable
     */
    public function atomic(callable $callback, bool $savepoint = false)
    {
        if (!$this->inAtomic) {
            $this->needsRollback = false;
            if ($this->inTransaction()) {
                $this->inAtomic = true;
            }
        }

        if (!$this->inAtomic) {
            $this->beginTransaction();
            $this->inAtomic = true;
        } elseif ($savepoint && !$this->needsRollback) {
            $this->createSavepoint($savepointName = $this->generateAtomicSavepointName());
            $this->savepointNames[] = $savepointName;
        } else {
            $this->savepointNames[] = null;
        }

        try {
            return $callback($this);

        } catch (\Throwable $exception) {
            // We only need that block to know about $exception in finally {...}, just re-throw it
            throw $exception;

        } finally {
            if (!empty($this->savepointNames)) {
                $savepointName  = array_pop($this->savepointNames);
            } else {
                $this->inAtomic = false;
            }

            if (!empty($exception) || $this->needsRollback) {
                // either current $callback errored or some nested one, do a rollback
                $this->needsRollback = false;
                if (!$this->inAtomic) {
                    $this->rollback();
                } elseif (empty($savepointName)) {
                    $this->needsRollback = true;
                } else {
                    try {
                        $this->rollbackToSavepoint($savepointName);
                        $this->releaseSavepoint($savepointName);
                    } catch (\Exception $rse) {
                        $this->needsRollback = true;
                    }
                }

            } elseif (!$this->inAtomic) {
                // we are leaving the outermost atomic() block, commit
                try {
                    $this->commit();
                } catch (\Exception $ce) {
                    $this->rollback();
                    throw $ce;
                }

            } elseif (!empty($savepointName)) {
                // we are leaving the nested atomic() block and a savepoint was added in it
                try {
                    $this->releaseSavepoint($savepointName);
                } catch (\Exception $se) {
                    try {
                        $this->rollbackToSavepoint($savepointName);
                        $this->releaseSavepoint($savepointName);
                    } catch (\Exception $rse) {
                        $this->needsRollback = true;
                    }
                    throw $se;
                }
            }
        }
    }

    /**
     * Registers a callback that will execute when the transaction is committed
     *
     * @param callable $callback
     * @return $this
     */
    public function onCommit(callable $callback): self
    {
        if (!$this->inAtomic) {
            throw new exceptions\BadMethodCallException(
                "onCommit() can only be used within atomic() closures"
            );
        }
        $this->onCommitCallbacks[] = [$this->savepointNames, $callback];

        return $this;
    }

    /**
     * Registers a callback that will execute when the transaction is rolled back
     *
     * @param callable $callback
     * @return $this
     */
    public function onRollback(callable $callback): self
    {
        if (!$this->inAtomic) {
            throw new exceptions\BadMethodCallException(
                "onRollback() can only be used within atomic() closures"
            );
        }
        if (!$this->shutdownRegistered) {
            register_shutdown_function(function () {
                $this->runAndClearOnRollbackCallbacks();
            });
            $this->shutdownRegistered = true;
        }
        $this->onRollbackCallbacks[] = [$this->savepointNames, $callback];

        return $this;
    }

    /**
     * Resets various fields used by atomic()
     *
     * Generally needed when a new DB connection is established
     */
    private function resetTransactionState(): void
    {
        $this->inAtomic            = false;
        $this->needsRollback       = false;
        $this->savepointNames      = [];
        $this->onCommitCallbacks   = [];
        $this->onRollbackCallbacks = [];
    }

    /**
     * Runs registered after-rollback callbacks and clears the list
     *
     * @param string $savepoint Name of the savepoint if rolling back to one
     */
    private function runAndClearOnRollbackCallbacks(string $savepoint = null): void
    {
        if (null === $savepoint) {
            [$callbacks, $this->onRollbackCallbacks] = [$this->onRollbackCallbacks, []];
        } else {
            $callbacks = [];
            foreach (array_keys($this->onRollbackCallbacks) as $key) {
                if (in_array($savepoint, $this->onRollbackCallbacks[$key][0])) {
                    $callbacks[] = $this->onRollbackCallbacks[$key];
                    unset($this->onRollbackCallbacks[$key]);
                }
            }
        }
        foreach ($callbacks as [, $callback]) {
            $callback();
        }
    }

    /**
     * Runs registered after-commit callbacks and clears the list
     */
    private function runAndClearOnCommitCallbacks(): void
    {
        [$callbacks, $this->onCommitCallbacks] = [$this->onCommitCallbacks, []];
        foreach ($callbacks as [, $callback]) {
            $callback();
        }
    }

    /**
     * Returns a savepoint name for use in atomic() blocks
     *
     * @return string
     */
    private function generateAtomicSavepointName(): string
    {
        return 'atomic_' . ++$this->savepointIndex;
    }
}
