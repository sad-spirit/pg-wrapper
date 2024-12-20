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

use PgSql\Connection as NativeConnection;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class representing a connection to the database
 */
class Connection
{
    /** Native connection object */
    private ?NativeConnection $native = null;

    /** Connection string (as used for pg_connect()) */
    private readonly string $connectionString;

    /** Type conversion factory for this connection */
    private ?TypeConverterFactory $converterFactory = null;

    /** Cache for database metadata */
    private ?CacheItemPoolInterface $cacheItemPool = null;

    /** Marks whether the connection is in a transaction managed by {@link atomic()} */
    private bool $inAtomic = false;

    /** Marks whether transaction should be rolled back to the next available savepoint due to error in inner block */
    private bool $needsRollback = false;

    /** Counter used to generate unique savepoint names */
    private int $savepointIndex = 0;

    /**
     * Names of savepoints created by {@link atomic()}
     * @var array<int, string|null>
     */
    private array $savepointNames = [];

    /**
     * Callbacks to run after successful commit of transaction
     *
     * Each entry is an array with two elements
     *  - Names of savepoints active when callback was registered
     *  - Actual callback
     *
     * @var array<int, array{array<int, string|null>, callable}>
     */
    private array $onCommitCallbacks = [];

    /**
     * Callbacks to run after rollback of transaction
     *
     * Each entry is an array with three elements
     *  - Names of savepoints active when callback was registered
     *  - Actual callback
     *  - Whether it should be run on commit as well (for rolled back savepoints in committed transaction)
     *
     * @var array<int, array{array<int, string|null>, callable, bool}>
     */
    private array $onRollbackCallbacks = [];

    /** Whether a shutdown function to run outstanding onRollback() callbacks was registered */
    private bool $shutdownRegistered = false;

    /**
     * Whether disconnect() method was called
     *
     * We connect to the database automatically only once: on first getNative() call if $lazy = true was passed
     * to constructor. Once disconnect() was ever called, we require manual call to connect().
     */
    private bool $disconnected = false;

    /** Whether disconnect() was called within atomic() closure */
    private bool $disconnectedInAtomic = false;

    /**
     * Constructor.
     *
     * @param string $connectionString Connection string.
     * @param bool   $lazy             Whether to postpone connecting until needed.
     * @throws exceptions\ConnectionException
     * @throws exceptions\RuntimeException
     */
    public function __construct(
        #[\SensitiveParameter]
        string $connectionString,
        bool $lazy = true
    ) {
        if (!\function_exists('pg_connect')) {
            throw new exceptions\RuntimeException("PHP's pgsql extension should be enabled");
        }
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
        $this->disconnectImpl();
    }

    /**
     * Forces opening a new database connection for cloned object
     */
    public function __clone()
    {
        $this->native             = null;
        $this->shutdownRegistered = false;
        $this->disconnected       = false;
        $this->converterFactory   = null;
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
        if ($this->isConnected()) {
            return $this;
        }

        $connectionWarnings = [];
        \set_error_handler(function (int $errno, string $errstr) use (&$connectionWarnings): true {
            $connectionWarnings[] = $errstr;
            return true;
        }, \E_WARNING);

        $native = \pg_connect($this->connectionString, \PGSQL_CONNECT_FORCE_NEW);

        \restore_error_handler();
        if (false === $native) {
            throw new exceptions\ConnectionException(
                __METHOD__ . ': ' . \implode("\n", $connectionWarnings)
            );
        }
        $this->native  = $native;
        $serverVersion = \pg_parameter_status($this->native, 'server_version');
        if (!$serverVersion || \version_compare($serverVersion, '12', '<')) {
            $this->disconnect();
            throw new exceptions\ConnectionException(
                __METHOD__ . ': PostgreSQL versions earlier than 12 are no longer supported, '
                . 'connected server reports ' . ($serverVersion ? 'version ' . $serverVersion : 'unknown version')
            );
        }
        \pg_set_error_verbosity($this->native, \PGSQL_ERRORS_VERBOSE);

        $this->resetTransactionState();

        return $this;
    }

    /**
     * Disconnects from the database
     *
     * @return $this
     */
    public function disconnect(): self
    {
        $this->disconnectImpl();

        return $this;
    }

    /**
     * Closes the connection and possibly runs registered "on rollback" callbacks
     *
     * This is a separate method returning void as calling disconnect() returning $this in __destruct() leads
     * to breakage of PHPUnit mocks for this class: https://github.com/sebastianbergmann/phpunit/issues/5809
     *
     * @return void
     */
    private function disconnectImpl(): void
    {
        if (null !== $this->native) {
            try {
                \pg_close($this->native);
            } catch (\Throwable) {
            }
        }
        $this->native       = null;
        $this->disconnected = true;

        // If disconnected while transaction is active, the transaction will be rolled back by the server,
        // thus run the relevant callbacks
        if (!$this->inAtomic) {
            // Run callbacks immediately
            $this->runAndClearOnRollbackCallbacks();
        } else {
            // Postpone running callbacks until exit from outermost atomic()
            $this->disconnectedInAtomic = true;
        }
    }

    /**
     * Checks whether a connection was made
     */
    public function isConnected(): bool
    {
        try {
            return null !== $this->native
                   && \PGSQL_CONNECTION_OK === \pg_connection_status($this->native);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Returns the last error message for this connection, null if none present
     */
    public function getLastError(): ?string
    {
        try {
            if (null !== $this->native && ($error = \pg_last_error($this->native))) {
                return $error;
            }
        } catch (\Throwable) {
        }
        return null;
    }

    /**
     * Returns the native object representing database connection
     *
     * @throws exceptions\ConnectionException
     */
    public function getNative(): NativeConnection
    {
        if (!$this->disconnected) {
            if (!$this->native instanceof NativeConnection) {
                $this->connect();
            } else {
                try {
                    \pg_connection_status($this->native);
                } catch (\Throwable) {
                    throw new exceptions\ConnectionException("Connection has been closed");
                }
            }
        }
        if (!$this->native instanceof NativeConnection) {
            throw new exceptions\ConnectionException("Connection has been closed");
        }
        return $this->native;
    }

    /**
     * Returns a unique identifier for connection
     *
     * @return string
     */
    public function getConnectionId(): string
    {
        return 'pg' . \sprintf('%x', \crc32(static::class . ' ' . $this->connectionString));
    }

    /**
     * Quotes a value for inclusion in query, taking connection encoding into account
     *
     * @param mixed $value
     * @param mixed $type
     * @return string
     * @throws exceptions\TypeConversionException
     * @throws exceptions\RuntimeException
     */
    public function quote(mixed $value, mixed $type = null): string
    {
        if (null === $value) {
            return 'NULL';
        }

        $native    = $this->getNative(); // forces connecting if not connected yet
        $converted = null !== $type
                     ? $this->getTypeConverter($type)->output($value)
                     : $this->getTypeConverterFactory()->getConverterForPHPValue($value)->output($value);
        $escaped   = null === $converted ? 'NULL' : @\pg_escape_literal($native, $converted);

        // PHP docs and psalm claim that pg_escape_literal() cannot return false,
        // phpstan and source of ext/pgsql think otherwise; let's take the side of caution
        if (false !== $escaped) {
            return $escaped;
        } else {
            throw new exceptions\RuntimeException(__METHOD__ . "(): pg_escape_literal() call failed");
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
        // PHP docs and psalm claim that pg_escape_identifier() cannot return false,
        // phpstan and source of ext/pgsql think otherwise; let's take the side of caution
        if (false !== ($escaped = @\pg_escape_identifier($this->getNative(), $identifier))) {
            return $escaped;
        } else {
            throw new exceptions\RuntimeException(__METHOD__ . "(): pg_escape_identifier() call failed");
        }
    }

    /**
     * Prepares a given query for execution.
     *
     * @param string                   $query       SQL query to prepare.
     * @param array<int, mixed>        $paramTypes  Types information used to convert input parameters
     * @param array<int|string, mixed> $resultTypes Result types to pass to ResultSet
     *
     * @return PreparedStatement Prepared statement.
     * @throws exceptions\ServerException
     */
    public function prepare(string $query, array $paramTypes = [], array $resultTypes = []): PreparedStatement
    {
        return new PreparedStatement($this, $query, $paramTypes, $resultTypes);
    }

    /**
     * Executes a given query
     *
     * @param string $sql         SQL query to execute
     * @param array  $resultTypes Type converters to pass to ResultSet
     *
     * @return Result Execution result.
     * @throws exceptions\ServerException
     */
    public function execute(string $sql, array $resultTypes = []): Result
    {
        $this->assertRollbackNotNeeded();
        return Result::createFromReturnValue(
            @\pg_query($this->getNative(), $sql),
            $this,
            $resultTypes
        );
    }

    /**
     * Executes a given query with the ability to pass parameters separately
     *
     * @param string $sql                             Query
     * @param array<int, mixed>        $params      Parameters
     * @param array<int, mixed>        $paramTypes  Types information used to convert input parameters
     * @param array<int|string, mixed> $resultTypes Result types to pass to ResultSet
     *
     * @return Result
     * @throws exceptions\ServerException
     */
    public function executeParams(string $sql, array $params, array $paramTypes = [], array $resultTypes = []): Result
    {
        $this->assertRollbackNotNeeded();

        $native       = $this->getNative();
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

        return Result::createFromReturnValue(
            @\pg_query_params($native, $sql, $stringParams),
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
            $this->setTypeConverterFactory($factory = new converters\DefaultTypeConverterFactory());
            return $factory;
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
        $this->converterFactory = $factory->setConnection($this);

        return $this;
    }

    /**
     * Returns type converter for the given database type
     */
    public function getTypeConverter(mixed $type): TypeConverter
    {
        return $this->getTypeConverterFactory()->getConverterForTypeSpecification($type);
    }

    /**
     * Returns the DB metadata cache
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

        $this->needsRollback = false;

        $this->execute('ROLLBACK');

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

        $this->onCommitCallbacks = \array_filter(
            $this->onCommitCallbacks,
            fn($value): bool => !\in_array($savepoint, $value[0])
        );
        \array_walk($this->onRollbackCallbacks, function (array &$value) use ($savepoint): void {
            if (\in_array($savepoint, $value[0])) {
                $value[2] = true;
            }
        });

        return $this;
    }

    /**
     * Checks whether a transaction is currently open.
     *
     * @return  bool
     */
    public function inTransaction(): bool
    {
        $status = \pg_transaction_status($this->getNative());

        return \PGSQL_TRANSACTION_INTRANS === $status || \PGSQL_TRANSACTION_INERROR === $status;
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
    public function atomic(callable $callback, bool $savepoint = false): mixed
    {
        if (!$this->inAtomic) {
            $this->assertRollbackNotNeeded();
            if ($this->inTransaction()) {
                $inTransaction  = true;
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
            if ($exception instanceof exceptions\ConnectionException) {
                // Connection is probably unusable anyway
                $this->disconnect();
            }
            throw $exception;

        } finally {
            if (!empty($this->savepointNames)) {
                $savepointName  = \array_pop($this->savepointNames);
            } else {
                $this->inAtomic = false;
            }

            try {
                /** @noinspection PhpStatementHasEmptyBodyInspection */
                if ($this->disconnectedInAtomic) {
                    // No-op

                } elseif (!empty($exception) || $this->needsRollback) {
                    // either current $callback errored or some nested one, do a rollback
                    if (!$this->inAtomic) {
                        $this->rollback();
                    } elseif (empty($savepointName)) {
                        $this->needsRollback = true;
                    } else {
                        $this->needsRollback = false;
                        try {
                            $this->rollbackToSavepoint($savepointName);
                            $this->releaseSavepoint($savepointName);
                        } catch (\Throwable) {
                            $this->needsRollback = true;
                        }
                    }

                } elseif (!$this->inAtomic) {
                    // we are leaving the outermost atomic() block, commit
                    try {
                        $this->commit();
                    } catch (\Throwable $ce) {
                        $this->rollback();
                        throw $ce;
                    }

                } elseif (!empty($savepointName)) {
                    // we are leaving the nested atomic() block and a savepoint was added in it
                    try {
                        $this->releaseSavepoint($savepointName);
                    } catch (\Throwable $se) {
                        try {
                            $this->rollbackToSavepoint($savepointName);
                            $this->releaseSavepoint($savepointName);
                        } catch (\Throwable) {
                            $this->needsRollback = true;
                        }
                        throw $se;
                    }
                }

            } catch (exceptions\ConnectionException $connectionException) {
                // Connection is probably unusable anyway
                $this->disconnect();
                throw $connectionException;

            } finally {
                // Covers the case when outermost atomic() was entered with transaction already open
                if (!empty($inTransaction) && 0 === \count($this->savepointNames)) {
                    $this->inAtomic = false;
                }
                // If disconnected from DB, run callbacks on exit from outermost atomic()
                if (!$this->inAtomic && $this->disconnectedInAtomic) {
                    $this->runAndClearOnRollbackCallbacks();
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
            \register_shutdown_function($this->runAndClearOnRollbackCallbacks(...));
            $this->shutdownRegistered = true;
        }
        $this->onRollbackCallbacks[] = [$this->savepointNames, $callback, false];

        return $this;
    }

    /**
     * Whether transaction should be rolled back due to an error in an inner block
     */
    public function needsRollback(): bool
    {
        return $this->needsRollback;
    }

    /**
     * Sets the $needsRollback flag for the current transaction
     *
     * This should *only* be used when doing some custom error handling within atomic() closures,
     * as incorrectly setting the flag will break transaction processing
     *
     * @param bool $needsRollback
     * @return $this
     */
    public function setNeedsRollback(bool $needsRollback): self
    {
        if (!$this->inAtomic) {
            throw new exceptions\BadMethodCallException(
                "setNeedsRollback() can only be used within atomic() closures"
            );
        }

        $this->needsRollback = $needsRollback;

        return $this;
    }

    /**
     * Throws an exception if $needsRollback flag was previously set, preventing queries except "ROLLBACK"
     *
     * @throws exceptions\RuntimeException
     */
    public function assertRollbackNotNeeded(): void
    {
        if ($this->needsRollback) {
            throw new exceptions\RuntimeException(
                "An error occurred in current transaction and it is marked for rollback."
                . " No queries will be accepted."
            );
        }
    }

    /**
     * Resets various fields used by atomic()
     *
     * Generally needed when a new DB connection is established
     */
    private function resetTransactionState(): void
    {
        $this->inAtomic             = false;
        $this->needsRollback        = false;
        $this->savepointNames       = [];
        $this->onCommitCallbacks    = [];
        $this->onRollbackCallbacks  = [];
        $this->disconnectedInAtomic = false;
    }

    /**
     * Runs registered after-rollback callbacks and clears the list
     */
    private function runAndClearOnRollbackCallbacks(): void
    {
        $this->onCommitCallbacks = [];
        [$callbacks, $this->onRollbackCallbacks] = [$this->onRollbackCallbacks, []];
        foreach ($callbacks as [, $callback]) {
            $callback();
        }
    }

    /**
     * Runs registered after-commit callbacks and clears the list
     */
    private function runAndClearOnCommitCallbacks(): void
    {
        $callbacks = \array_merge(
            $this->onCommitCallbacks,
            \array_filter($this->onRollbackCallbacks, fn($value): bool => $value[2])
        );
        $this->onCommitCallbacks   = [];
        $this->onRollbackCallbacks = [];

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
