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

namespace sad_spirit\pg_wrapper\decorators;

use sad_spirit\pg_wrapper\{
    Connection,
    PreparedStatement,
    Result,
    TypeConverter,
    TypeConverterFactory
};
use PgSql\Connection as NativeConnection;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Decorator for Connection class
 *
 * This base class delegates all method calls to the decorated instance, child classes will implement
 * the additional logic
 */
abstract class ConnectionDecorator extends Connection
{
    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct(private readonly Connection $wrapped)
    {
    }

    public function __destruct()
    {
        // No-op, logic from parent __destruct() is not needed here
    }

    public function __clone()
    {
        // No-op, logic from parent __clone() is not needed here
    }

    public function connect(): self
    {
        $this->wrapped->connect();

        return $this;
    }


    public function disconnect(): self
    {
        $this->wrapped->disconnect();

        return $this;
    }

    public function isConnected(): bool
    {
        return $this->wrapped->isConnected();
    }

    public function getLastError(): ?string
    {
        return $this->wrapped->getLastError();
    }

    public function getNative(): NativeConnection
    {
        return $this->wrapped->getNative();
    }

    public function getConnectionId(): string
    {
        return $this->wrapped->getConnectionId();
    }

    public function quote(mixed $value, mixed $type = null): string
    {
        return $this->wrapped->quote($value, $type);
    }

    public function quoteIdentifier(string $identifier): string
    {
        return $this->wrapped->quoteIdentifier($identifier);
    }

    public function prepare(string $query, array $paramTypes = [], array $resultTypes = []): PreparedStatement
    {
        return $this->wrapped->prepare($query, $paramTypes, $resultTypes);
    }

    public function execute(string $sql, array $resultTypes = []): Result
    {
        return $this->wrapped->execute($sql, $resultTypes);
    }

    public function executeParams(string $sql, array $params, array $paramTypes = [], array $resultTypes = []): Result
    {
        return $this->wrapped->executeParams($sql, $params, $paramTypes, $resultTypes);
    }

    public function getTypeConverterFactory(): TypeConverterFactory
    {
        return $this->wrapped->getTypeConverterFactory();
    }

    public function setTypeConverterFactory(TypeConverterFactory $factory): self
    {
        $this->wrapped->setTypeConverterFactory($factory);

        return $this;
    }

    public function getTypeConverter(mixed $type): TypeConverter
    {
        return $this->wrapped->getTypeConverter($type);
    }

    public function getMetadataCache(): ?CacheItemPoolInterface
    {
        return $this->wrapped->getMetadataCache();
    }

    public function setMetadataCache(CacheItemPoolInterface $cache): self
    {
        $this->wrapped->setMetadataCache($cache);

        return $this;
    }

    public function beginTransaction(): self
    {
        $this->wrapped->beginTransaction();

        return $this;
    }

    public function commit(): self
    {
        $this->wrapped->commit();

        return $this;
    }

    public function rollback(): self
    {
        $this->wrapped->rollback();

        return $this;
    }

    public function createSavepoint(string $savepoint): self
    {
        $this->wrapped->createSavepoint($savepoint);

        return $this;
    }

    public function releaseSavepoint(string $savepoint): self
    {
        $this->wrapped->releaseSavepoint($savepoint);

        return $this;
    }

    public function rollbackToSavepoint(string $savepoint): self
    {
        $this->wrapped->rollbackToSavepoint($savepoint);

        return $this;
    }

    public function inTransaction(): bool
    {
        return $this->wrapped->inTransaction();
    }

    public function atomic(callable $callback, bool $savepoint = false): mixed
    {
        return $this->wrapped->atomic(fn() => $callback($this), $savepoint);
    }

    public function onCommit(callable $callback): self
    {
        $this->wrapped->onCommit($callback);

        return $this;
    }

    public function onRollback(callable $callback): self
    {
        $this->wrapped->onRollback($callback);

        return $this;
    }

    public function needsRollback(): bool
    {
        return $this->wrapped->needsRollback();
    }

    public function setNeedsRollback(bool $needsRollback): self
    {
        $this->wrapped->setNeedsRollback($needsRollback);

        return $this;
    }

    public function assertRollbackNotNeeded(): void
    {
        $this->wrapped->assertRollbackNotNeeded();
    }
}
