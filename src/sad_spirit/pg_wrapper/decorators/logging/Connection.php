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

namespace sad_spirit\pg_wrapper\decorators\logging;

use sad_spirit\pg_wrapper\{
    Connection as BaseConnection,
    Result,
    decorators\ConnectionDecorator
};
use Psr\Log\LoggerInterface;

/**
 * Decorator for Connection that logs queries using an implementation of PSR-3 LoggerInterface
 */
final class Connection extends ConnectionDecorator
{
    public function __construct(BaseConnection $wrapped, private readonly LoggerInterface $logger)
    {
        parent::__construct($wrapped);
    }

    public function connect(): self
    {
        $this->logger->debug(
            'Connecting explicitly, connection ID {connectionId}',
            ['connectionId' => $this->getConnectionId()]
        );

        return parent::connect();
    }

    public function disconnect(): self
    {
        $this->logger->debug(
            'Disconnecting explicitly, connection ID {connectionId}',
            ['connectionId' => $this->getConnectionId()]
        );

        return parent::disconnect();
    }

    public function execute(string $sql, array $resultTypes = []): Result
    {
        $this->logger->debug('Executing statement {sql}', ['sql' => $sql]);

        return parent::execute($sql, $resultTypes);
    }

    public function executeParams(string $sql, array $params, array $paramTypes = [], array $resultTypes = []): Result
    {
        $this->logger->debug(
            'Executing statement {sql} with parameters {params}, types {types}',
            ['sql' => $sql, 'params' => $params, 'types' => $paramTypes]
        );

        return parent::executeParams($sql, $params, $paramTypes, $resultTypes);
    }

    public function prepare(string $query, array $paramTypes = [], array $resultTypes = []): PreparedStatement
    {
        return new PreparedStatement(
            parent::prepare($query, $paramTypes, $resultTypes),
            $this->logger,
            $query
        );
    }

    public function beginTransaction(): self
    {
        $this->logger->debug("Beginning transaction");

        return parent::beginTransaction();
    }

    public function commit(): self
    {
        $this->logger->debug("Committing transaction");

        return parent::commit();
    }

    public function rollback(): self
    {
        $this->logger->debug("Rolling back transaction");

        return parent::rollback();
    }

    public function createSavepoint(string $savepoint): self
    {
        $this->logger->debug('Creating savepoint {name}', ['name' => $savepoint]);

        return parent::createSavepoint($savepoint);
    }

    public function releaseSavepoint(string $savepoint): self
    {
        $this->logger->debug('Releasing savepoint {name}', ['name' => $savepoint]);

        return parent::releaseSavepoint($savepoint);
    }

    public function rollbackToSavepoint(string $savepoint): self
    {
        $this->logger->debug('Rolling back to savepoint {name}', ['name' => $savepoint]);

        return parent::rollbackToSavepoint($savepoint);
    }
}
